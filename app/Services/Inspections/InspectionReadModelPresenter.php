<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Services\Demo\ViewFirstDemoPresenter;

/**
 * Operational inspection read model. The compatibility parent is kept private
 * to this transition so existing persisted payload contracts remain stable.
 */
final class InspectionReadModelPresenter extends ViewFirstDemoPresenter {}
