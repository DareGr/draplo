<?php

namespace App\Enums;

enum UserPlanEnum: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Subscriber = 'subscriber';
}
