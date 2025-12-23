<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\State;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class DebugModeCheck extends Check
{
    protected string $type = 'debug_mode';

    public function __construct(
        protected readonly State $appState
    ) {}

    public function run(): ResultData
    {
        try {
            $mode = $this->appState->getMode();
            $isDeveloper = $mode === State::MODE_DEVELOPER;
            $status = $isDeveloper ? Status::Warning : Status::Healthy;
            $message = $isDeveloper
                ? 'Application is running in developer mode.'
                : sprintf('Application is running in %s mode.', $mode);
        } catch (Throwable $exception) {
            $status = Status::Unhealthy;
            $message = 'Unable to determine application mode: ' . $exception->getMessage();
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        return true;
    }
}
