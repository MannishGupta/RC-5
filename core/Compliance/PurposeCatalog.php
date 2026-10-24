<?php
// Version: 3.2.0
declare(strict_types=1);

/** DPDP purpose IDs for consent logging */
final class PurposeCatalog
{
    public const ESSENTIAL = 'essential_ops';
    public const ANALYTICS = 'analytics_optional';
    public const DIRECTORY = 'directory_public';
    public const MARKETING = 'marketing_optional';

    /** @return array<string, array{label_en:string,label_hi:string,essential:bool}> */
    public static function all(): array
    {
        return [
            self::ESSENTIAL => [
                'label_en' => 'Essential operations (session, security, load balancing)',
                'label_hi' => 'आवश्यक संचालन (सत्र, सुरक्षा)',
                'essential' => true,
            ],
            self::ANALYTICS => [
                'label_en' => 'Optional analytics',
                'label_hi' => 'वैकल्पिक विश्लेषण',
                'essential' => false,
            ],
            self::DIRECTORY => [
                'label_en' => 'Public directory / digital card visibility',
                'label_hi' => 'सार्वजनिक निर्देशिका / कार्ड',
                'essential' => false,
            ],
            self::MARKETING => [
                'label_en' => 'Optional product communications',
                'label_hi' => 'वैकल्पिक उत्पाद सूचनाएँ',
                'essential' => false,
            ],
        ];
    }

    public static function isValid(string $id): bool
    {
        return isset(self::all()[$id]);
    }
}
