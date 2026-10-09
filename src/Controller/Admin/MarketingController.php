<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Entity\Marketing\CampaignRepository;
use AppBundle\Form\Marketing\CampaignType;
use AppBundle\Service\Marketing\CampaignAudienceResolver;
use AppBundle\Service\Marketing\CampaignNotSendableException;
use AppBundle\Service\Marketing\CampaignSender;
use AppBundle\Service\Marketing\CampaignTemplateProvider;
use AppBundle\Service\Marketing\MarketingAutomationStatus;
use AppBundle\Service\Marketing\MarketingMailer;
use AppBundle\Service\Marketing\MarketingMailerNotConfiguredException;
use AppBundle\Service\RfmSegmentCalculator;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MarketingController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly bool $marketingAutomationEnabled,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignRecipientRepository $recipientRepository,
        private readonly CampaignTemplateProvider $templateProvider,
        private readonly CampaignAudienceResolver $audienceResolver,
        private readonly MarketingMailer $marketingMailer,
        private readonly RendererInterface $mjml,
    ) {}

    #[Route('/admin/marketing', name: 'admin_marketing', methods: ['GET', 'POST'])]
    public function indexAction(Request $request, PaginatorInterface $paginator): Response
    {
        $this->denyAccess();

        $activeForm = $this->container->get('form.factory')->createNamedBuilder(
            'marketing_automation_active_form',
            FormType::class,
            ['active' => $this->settingsManager->getBoolean('marketing_automation_active')],
            ['data_class' => null]
        )
            ->add('active', CheckboxType::class, ['required' => false, 'label' => false])
            ->getForm();
        $activeForm->handleRequest($request);

        if ($activeForm->isSubmitted()) {
            $active = (bool) $activeForm->get('active')->getData();
            $this->settingsManager->set('marketing_automation_active', $active ? '1' : '0');
            $this->settingsManager->flush();
            $this->addFlash('notice', $active ? 'marketing.program.activated' : 'marketing.program.deactivated');

            return $this->redirectToRoute('admin_marketing');
        }

        $campaigns = $paginator->paginate(
            $this->campaignRepository->createListQueryBuilder(),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        $stats = [];
        foreach ($campaigns as $campaign) {
            $stats[$campaign->getId()] = $this->recipientRepository->countByCampaignAndStatus($campaign);
        }

        return $this->render('admin/marketing/index.html.twig', [
            'active_form' => $activeForm,
            'is_active' => $this->settingsManager->getBoolean('marketing_automation_active'),
            'is_configured' => $this->marketingMailer->isConfigured(),
            'campaigns' => $campaigns,
            'stats' => $stats,
            'segments' => RfmSegmentCalculator::SEGMENTS,
            'segments_with_strategy' => CampaignTemplateProvider::SEGMENTS_WITH_STRATEGY,
        ]);
    }

    #[Route('/admin/marketing/campaigns/new/{segment}', name: 'admin_marketing_campaign_new', methods: ['POST'])]
    public function newAction(string $segment): Response
    {
        $this->denyAccess();

        if (!in_array($segment, RfmSegmentCalculator::SEGMENTS, true)) {
            throw $this->createNotFoundException();
        }

        // Pre-filled from the segment's strategy, so the admin starts by
        // editing something rather than facing a blank page.
        $campaign = new Campaign();
        $campaign->setSegment($segment);
        $campaign->setName($this->templateProvider->getName($segment));
        $campaign->setSubject($this->templateProvider->getSubject($segment));
        $campaign->setBodyMjml($this->templateProvider->getBodyMjml($segment));
        $campaign->setBodyHtml($this->mjml->render($campaign->getBodyMjml()));

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin_marketing_campaign', ['id' => $campaign->getId()]);
    }

    #[Route('/admin/marketing/campaigns/{id}', name: 'admin_marketing_campaign', methods: ['GET', 'POST'])]
    public function campaignAction(int $id, Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccess();

        $campaign = $this->findCampaign($id);

        $form = $this->createForm(CampaignType::class, $campaign, [
            'disabled' => !$campaign->isEditable(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $campaign->isEditable()) {
            // The editor works in MJML; what gets sent is the rendered HTML,
            // so it's regenerated on every save rather than at send time --
            // a broken template should fail here, in front of the person who
            // broke it.
            $campaign->setBodyHtml($this->mjml->render((string) $campaign->getBodyMjml()));

            $campaign->setStatus(
                null === $campaign->getScheduledAt() ? Campaign::STATUS_DRAFT : Campaign::STATUS_SCHEDULED
            );

            $this->entityManager->flush();
            $this->addFlash('notice', $translator->trans('marketing.campaign.saved'));

            return $this->redirectToRoute('admin_marketing_campaign', ['id' => $campaign->getId()]);
        }

        // Only worth the RFM query while the campaign can still be changed;
        // once it's gone out, what matters is who it actually reached.
        $audience = $campaign->isEditable()
            ? $this->audienceResolver->resolve($campaign->getSegment())
            : null;

        return $this->render('admin/marketing/campaign.html.twig', [
            'campaign' => $campaign,
            'form' => $form,
            'audience' => $audience,
            'stats' => $this->recipientRepository->countByCampaignAndStatus($campaign),
            'is_configured' => $this->marketingMailer->isConfigured(),
            'is_active' => $this->settingsManager->getBoolean('marketing_automation_active'),
            'frequency_cap_days' => $this->audienceResolver->getFrequencyCapDays(),
        ]);
    }

    #[Route('/admin/marketing/campaigns/{id}/send', name: 'admin_marketing_campaign_send', methods: ['POST'])]
    public function sendAction(
        int $id,
        Request $request,
        CampaignSender $campaignSender,
        MarketingAutomationStatus $marketingAutomationStatus,
        TranslatorInterface $translator): Response
    {
        $this->denyAccess();

        $campaign = $this->findCampaign($id);

        if (!$this->isCsrfTokenValid('admin_marketing_campaign_send', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$marketingAutomationStatus->isActive()) {
            $this->addFlash('error', $translator->trans('marketing.campaign.send.program_inactive'));

            return $this->redirectToRoute('admin_marketing_campaign', ['id' => $id]);
        }

        try {
            $audience = $campaignSender->send($campaign);
        } catch (CampaignNotSendableException | MarketingMailerNotConfiguredException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_marketing_campaign', ['id' => $id]);
        }

        $this->addFlash('notice', $translator->trans('marketing.campaign.send.queued', [
            '%count%' => $audience->count(),
        ]));

        return $this->redirectToRoute('admin_marketing_campaign', ['id' => $id]);
    }

    #[Route('/admin/marketing/campaigns/{id}/delete', name: 'admin_marketing_campaign_delete', methods: ['POST'])]
    public function deleteAction(int $id, Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccess();

        $campaign = $this->findCampaign($id);

        if (!$this->isCsrfTokenValid('admin_marketing_campaign_delete', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // A sent campaign is a record of who was emailed, which the
        // frequency cap reads. Deleting it would let those people be
        // emailed again immediately.
        if (!$campaign->isEditable()) {
            $this->addFlash('error', $translator->trans('marketing.campaign.delete.already_sent'));

            return $this->redirectToRoute('admin_marketing_campaign', ['id' => $id]);
        }

        $this->entityManager->remove($campaign);
        $this->entityManager->flush();

        $this->addFlash('notice', $translator->trans('marketing.campaign.deleted'));

        return $this->redirectToRoute('admin_marketing');
    }

    private function findCampaign(int $id): Campaign
    {
        $campaign = $this->campaignRepository->find($id);

        if (null === $campaign) {
            throw $this->createNotFoundException();
        }

        return $campaign;
    }

    private function denyAccess(): void
    {
        if (!$this->marketingAutomationEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }
}
