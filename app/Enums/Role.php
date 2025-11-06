<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case ALGEMEEN_MEDEWERKER = 'algemeen_medewerker';
    case GEBRUIKER = 'gebruiker';
    case CUSTOMER_SERVICE = 'customer_service';
    case FACILITIES_COORDINATOR = 'facilities_coordinator';

    public function label(): string
    {
        switch ($this) {
            case self::ADMIN:
                return 'Admin';
            case self::ALGEMEEN_MEDEWERKER:
                return 'Algemeen medewerker';
            case self::GEBRUIKER:
                return 'Gebruiker';
            case self::CUSTOMER_SERVICE:
                return 'Klantenservice';
            case self::FACILITIES_COORDINATOR:
                return 'Faciliteiten coördinator';
            default:
                return 'Unknown';
        }
    }
}
