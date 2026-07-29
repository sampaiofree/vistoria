<?php

namespace App\Enums;

enum UserAccountType: string
{
    case SuperAdmin = 'super_admin';

    case CompanyAdmin = 'company_admin';

    case Member = 'member';
}
