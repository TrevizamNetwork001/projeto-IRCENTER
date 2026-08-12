<?php

namespace App\Modules\Fiscal\Enums;

enum FiscalEnvironment: string
{
    case Homologation = 'homologation';
    case Production = 'production';
}
