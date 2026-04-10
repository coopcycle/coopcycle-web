<?php

namespace AppBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

class EmailTemplateManager
{
    /**
     * Customer-facing email types exposed in the editor.
     * Internal emails (admin/owner/dispatcher) are intentionally excluded.
     */
    public const CUSTOMER_EMAILS = [
        'order_created' => [
            'label'     => 'Order Confirmed (Customer)',
            'variables' => ['brand_name', 'order_number', 'order_url'],
        ],
        'order_accepted' => [
            'label'     => 'Order Accepted',
            'variables' => ['brand_name', 'order_number', 'order_url'],
        ],
        'order_cancelled' => [
            'label'     => 'Order Cancelled',
            'variables' => ['brand_name', 'order_number'],
        ],
        'order_delayed' => [
            'label'     => 'Order Delayed',
            'variables' => ['brand_name', 'order_number', 'delay'],
        ],
        'order_payment' => [
            'label'     => 'Payment Confirmation',
            'variables' => ['brand_name', 'order_number'],
        ],
        'order_payment_failed' => [
            'label'     => 'Payment Failed',
            'variables' => ['brand_name', 'order_number'],
        ],
        'order_receipt' => [
            'label'     => 'Order Receipt',
            'variables' => ['brand_name', 'order_number'],
        ],
        'task_completed' => [
            'label'     => 'Delivery Completed / Failed',
            'variables' => ['brand_name', 'delivery_id', 'tracking_url'],
        ],
    ];

    public function __construct(
        private Filesystem $emailTemplatesFilesystem,
        private SettingsManager $settingsManager,
    ) {}

    public function getEmailTypes(): array
    {
        return self::CUSTOMER_EMAILS;
    }

    public function isValidType(string $type): bool
    {
        return isset(self::CUSTOMER_EMAILS[$type]);
    }

    /**
     * Returns the custom MJML stored in S3, or null if no customisation exists.
     */
    public function getCustomTemplate(string $type): ?string
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        try {
            if ($this->emailTemplatesFilesystem->fileExists($type . '.mjml')) {
                return $this->emailTemplatesFilesystem->read($type . '.mjml');
            }
        } catch (FilesystemException $e) {
            // fall through to default
        }

        return null;
    }

    /**
     * Saves a custom MJML template for the given type to S3.
     */
    public function saveTemplate(string $type, string $mjml): void
    {
        if (!$this->isValidType($type)) {
            throw new \InvalidArgumentException(sprintf('Unknown email type: %s', $type));
        }

        $this->emailTemplatesFilesystem->write($type . '.mjml', $mjml);
    }

    /**
     * Deletes the custom template so the default Twig template is used again.
     */
    public function deleteTemplate(string $type): void
    {
        if (!$this->isValidType($type)) {
            return;
        }

        try {
            if ($this->emailTemplatesFilesystem->fileExists($type . '.mjml')) {
                $this->emailTemplatesFilesystem->delete($type . '.mjml');
            }
        } catch (FilesystemException $e) {
            // ignore
        }
    }

    /**
     * Returns a complete MJML document to use as the starting point in the editor
     * when no custom template has been saved yet.
     */
    public function getDefaultMjml(string $type): string
    {
        $brandName = $this->settingsManager->get('brand_name') ?? '{{brand_name}}';

        $contents = [
            'order_created' => [
                'heading' => 'Order Confirmed!',
                'body'    => 'Thank you for your order <strong>#{{order_number}}</strong>! We have received it and it is being processed.',
                'cta'     => ['label' => 'View Order', 'href' => '{{order_url}}'],
            ],
            'order_accepted' => [
                'heading' => 'Order Accepted!',
                'body'    => 'Great news! Your order <strong>#{{order_number}}</strong> has been accepted and is being prepared.',
                'cta'     => ['label' => 'View Order', 'href' => '{{order_url}}'],
            ],
            'order_cancelled' => [
                'heading' => 'Order Cancelled',
                'body'    => 'We\'re sorry to inform you that your order <strong>#{{order_number}}</strong> has been cancelled. Please contact us if you have any questions.',
                'cta'     => null,
            ],
            'order_delayed' => [
                'heading' => 'Your Order Is Running Late',
                'body'    => 'We wanted to let you know that your order <strong>#{{order_number}}</strong> is running approximately <strong>{{delay}} minutes</strong> late. We apologise for the inconvenience.',
                'cta'     => null,
            ],
            'order_payment' => [
                'heading' => 'Payment Confirmed',
                'body'    => 'Your payment for order <strong>#{{order_number}}</strong> has been confirmed. Thank you!',
                'cta'     => null,
            ],
            'order_payment_failed' => [
                'heading' => 'Payment Failed',
                'body'    => 'Unfortunately, the payment for your order <strong>#{{order_number}}</strong> has failed. Please try again or contact us for help.',
                'cta'     => null,
            ],
            'order_receipt' => [
                'heading' => 'Your Receipt',
                'body'    => 'Please find attached the receipt for your order <strong>#{{order_number}}</strong>. Thank you for choosing ' . $brandName . '.',
                'cta'     => null,
            ],
            'task_completed' => [
                'heading' => 'Delivery Update',
                'body'    => 'Your delivery <strong>#{{delivery_id}}</strong> status has been updated.',
                'cta'     => ['label' => 'Track Delivery', 'href' => '{{tracking_url}}'],
            ],
        ];

        $c = $contents[$type] ?? ['heading' => 'Notification', 'body' => '', 'cta' => null];

        $ctaMjml = '';
        if ($c['cta'] !== null) {
            $ctaMjml = sprintf(
                "\n        <mj-button font-family=\"Raleway, Arial, sans-serif\" background-color=\"#10ac84\" color=\"white\" href=\"%s\">%s</mj-button>",
                $c['cta']['href'],
                $c['cta']['label']
            );
        }

        return <<<MJML
<mjml>
  <mj-head>
    <mj-font name="Raleway" href="https://fonts.googleapis.com/css?family=Raleway:400,700" />
    <mj-font name="Open Sans" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700" />
    <mj-attributes>
      <mj-text align="center" color="#555" />
      <mj-all font-family="'Open Sans', Arial, sans-serif" />
    </mj-attributes>
  </mj-head>
  <mj-body background-color="#eeeeee">
    <mj-section>
      <mj-column>
        <mj-text font-family="Raleway, Arial, sans-serif" align="left">
          <h2>{$brandName}</h2>
        </mj-text>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff">
      <mj-column>
        <mj-text align="left" line-height="24px">
          <h3>{$c['heading']}</h3>
          <p>{$c['body']}</p>
        </mj-text>{$ctaMjml}
      </mj-column>
    </mj-section>
    <mj-section>
      <mj-column>
        <mj-text align="center" font-size="12px" color="#999999">
          Powered by CoopCycle
        </mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
MJML;
    }

    /**
     * Renders a stored custom MJML by substituting the given variable map.
     * Returns null if no custom template is stored for the given type.
     */
    public function renderCustomTemplate(string $type, array $variables): ?string
    {
        $template = $this->getCustomTemplate($type);
        if ($template === null) {
            return null;
        }

        $search  = [];
        $replace = [];
        foreach ($variables as $key => $value) {
            $search[]  = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }
}
