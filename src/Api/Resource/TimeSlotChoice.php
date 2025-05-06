<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Contracts\Translation\TranslatorInterface;

#[ApiResource(operations: [new Get(controller: NotFoundAction::class, read: false, output: false)])]
final class TimeSlotChoice
{
    /**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;

    /**
     * @var string
     */
    #[Groups(['time_slot_choices'])]
    public $value;

    /**
     * @var string
     */
    #[Groups(['time_slot_choices'])]
    public $label;

    public function __construct(\DatePeriod $period, TranslatorInterface $translator, string $locale)
    {
        $this->id = Uuid::uuid4()->toString();

        $this->value = implode('/', [
            Carbon::instance($period->start)->tz('UTC')->toIso8601ZuluString(),
            Carbon::instance($period->end)->tz('UTC')->toIso8601ZuluString()
        ]);

        $calendar = Carbon::instance($period->start)
            ->locale($locale)
            ->calendar(null, [
                'sameDay' => '[' . $translator->trans('basics.today') . ']',
                'nextDay' => '[' . $translator->trans('basics.tomorrow') . ']',
                'nextWeek' => 'dddd',
            ]);

        $fmt = new \IntlDateFormatter($locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::SHORT);

        $this->label = $translator->trans('time_slot.human_readable', [
            '%day%' => ucfirst(strtolower($calendar)),
            '%start%' => $fmt->format($period->start),
            '%end%' => $fmt->format($period->end),
        ]);
    }
}
