<?php

namespace Surface\Contracts\Toolkits\Dialogs;

/** The icon and weight of an alert; null on Alert is the toolkit's plain alert. */
enum AlertStyle: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
