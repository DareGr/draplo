<?php

namespace App\Enums;

enum ProjectStatusEnum: string
{
    case Draft = 'draft';
    case WizardDone = 'wizard_done';
    case Generating = 'generating';
    case Generated = 'generated';
    case Exported = 'exported';
    case Exporting = 'exporting';
    case Deploying = 'deploying';
    case Deployed = 'deployed';
    case Failed = 'failed';
}
