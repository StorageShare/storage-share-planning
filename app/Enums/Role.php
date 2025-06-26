<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case ALGEMEEN_MEDEWERKER = 'algemeen_medewerker';
    case GEBRUIKER = 'gebruiker';
}
