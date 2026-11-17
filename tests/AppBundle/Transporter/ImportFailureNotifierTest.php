<?php

declare(strict_types=1);

namespace Tests\AppBundle\Transporter;

use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Transporter\ImportFailureNotifier;
use AppBundle\Transporter\RejectedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;

class ImportFailureNotifierTest extends KernelTestCase
{
    use ProphecyTrait;

    public function testRejectedFilesAreSentToTheAdministrator(): void
    {
        $message = new Email();
        $files = ['to_x/garbage.edi' => new RejectedFile('to_x/garbage.edi', 'Not an EDIFACT message', 'GARBAGE')];

        $emailManager = $this->prophesize(EmailManager::class);
        $emailManager->createImportFailedMessage('DBSCHENKER', $files)->willReturn($message);
        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get('administrator_email')->willReturn('admin@coopcycle.org');

        $notifier = new ImportFailureNotifier(
            $emailManager->reveal(),
            $settingsManager->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
        $notifier->notify('DBSCHENKER', $files);

        $emailManager->sendTo($message, 'admin@coopcycle.org')->shouldHaveBeenCalledOnce();
        // The CoopCycle developers are told too
        $this->assertSame(['dev@coopcycle.org'], array_map(fn($address) => $address->getAddress(), $message->getCc()));
    }

    public function testOnlyTheDevelopersAreToldWithoutAdministratorEmail(): void
    {
        $message = new Email();
        $files = [new RejectedFile('garbage.edi', 'Not an EDIFACT message', 'GARBAGE')];

        $emailManager = $this->prophesize(EmailManager::class);
        $emailManager->createImportFailedMessage('DBSCHENKER', $files)->willReturn($message);
        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get('administrator_email')->willReturn(null);
        $logger = $this->prophesize(LoggerInterface::class);

        $notifier = new ImportFailureNotifier($emailManager->reveal(), $settingsManager->reveal(), $logger->reveal());
        $notifier->notify('DBSCHENKER', $files);

        $emailManager->sendTo($message, 'dev@coopcycle.org')->shouldHaveBeenCalledOnce();
        $this->assertEmpty($message->getCc());
        $logger
            ->warning('No administrator_email set, the rejected files of DBSCHENKER are only sent to dev@coopcycle.org', ['source' => 'DBSCHENKER'])
            ->shouldHaveBeenCalledOnce();
    }

    public function testNothingIsSentWithoutRejectedFiles(): void
    {
        $emailManager = $this->prophesize(EmailManager::class);
        $settingsManager = $this->prophesize(SettingsManager::class);

        $notifier = new ImportFailureNotifier(
            $emailManager->reveal(),
            $settingsManager->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
        $notifier->notify('DBSCHENKER', []);

        $emailManager->sendTo(Argument::cetera())->shouldNotHaveBeenCalled();
        $settingsManager->get(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testAFailureToNotifyDoesNotFailTheImport(): void
    {
        $emailManager = $this->prophesize(EmailManager::class);
        $emailManager->createImportFailedMessage(Argument::cetera())->willThrow(new \RuntimeException('MJML is down'));
        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get('administrator_email')->willReturn('admin@coopcycle.org');
        $logger = $this->prophesize(LoggerInterface::class);

        $notifier = new ImportFailureNotifier($emailManager->reveal(), $settingsManager->reveal(), $logger->reveal());
        $notifier->notify('DBSCHENKER', [new RejectedFile('garbage.edi', 'Not an EDIFACT message', 'GARBAGE')]);

        $logger
            ->error('Failed to notify the rejected files of DBSCHENKER: MJML is down', Argument::withEntry('source', 'DBSCHENKER'))
            ->shouldHaveBeenCalledOnce();
    }

    public function testTheMessageListsAndAttachesTheFiles(): void
    {
        self::bootKernel();

        /** @var EmailManager $emailManager */
        $emailManager = self::getContainer()->get(EmailManager::class);
        $message = $emailManager->createImportFailedMessage('DBSCHENKER', [
            'to_x/garbage.edi' => new RejectedFile('to_x/garbage.edi', 'Not an <EDIFACT> message', 'GARBAGE'),
            'to_x/other.edi' => new RejectedFile('to_x/other.edi', 'No address', "UNA:+.? '\nUNB+UNOC:1"),
        ]);

        $this->assertStringContainsString('DBSCHENKER', $message->getSubject());
        $html = $message->getHtmlBody();
        $this->assertStringContainsString('to_x/garbage.edi', $html);
        // The reason comes from the file: escaped
        $this->assertStringContainsString('Not an &lt;EDIFACT&gt; message', $html);
        $this->assertStringContainsString('No address', $html);

        $attachments = array_values(array_filter(
            $message->getAttachments(),
            fn($part): bool => $part->getFilename() !== 'logo.png' && $part->getFilename() !== null
                && $part->getDisposition() === 'attachment'
        ));
        $this->assertCount(2, $attachments);
        $this->assertSame('garbage.edi', $attachments[0]->getFilename());
        $this->assertSame('GARBAGE', $attachments[0]->getBody());
        $this->assertSame('other.edi', $attachments[1]->getFilename());
        $this->assertSame("UNA:+.? '\nUNB+UNOC:1", $attachments[1]->getBody());
        $this->assertSame('text/plain', sprintf('%s/%s', $attachments[0]->getMediaType(), $attachments[0]->getMediaSubtype()));
    }

    public function testABinaryFileIsAttachedWithItsTypeAndExtension(): void
    {
        self::bootKernel();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'N° de livraison');
        $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_rejected_xlsx_');
        try {
            (new XlsxWriter($spreadsheet))->save($tempnam);
            $xlsx = file_get_contents($tempnam);
        } finally {
            unlink($tempnam);
        }

        /** @var EmailManager $emailManager */
        $emailManager = self::getContainer()->get(EmailManager::class);
        $message = $emailManager->createImportFailedMessage('DBSCHENKER', [
            // An importer's key doesn't have to end with the extension
            'Tour 1234' => new RejectedFile('Tour 1234', 'Unreadable file', $xlsx),
            'Tour.xlsx' => new RejectedFile('Tour.xlsx', 'Unreadable file', $xlsx),
        ]);

        $attachments = array_values(array_filter(
            $message->getAttachments(),
            fn($part): bool => $part->getDisposition() === 'attachment'
        ));
        $this->assertCount(2, $attachments);

        // With the extension added, so it opens as a spreadsheet
        $this->assertSame('Tour 1234.xlsx', $attachments[0]->getFilename());
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            sprintf('%s/%s', $attachments[0]->getMediaType(), $attachments[0]->getMediaSubtype())
        );
        $this->assertSame($xlsx, $attachments[0]->getBody());

        // Not twice
        $this->assertSame('Tour.xlsx', $attachments[1]->getFilename());
    }
}
