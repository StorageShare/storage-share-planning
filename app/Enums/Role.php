<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case ALGEMEEN_MEDEWERKER = 'algemeen_medewerker';
    case GEBRUIKER = 'gebruiker';
    case CUSTOMER_SERVICE = 'customer_service';
    case FACILITIES_COORDINATOR = 'facilities_coordinator';
}
