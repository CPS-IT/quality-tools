<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Messaging\OutputCollectorInterface;

interface ToolRunnerInterface
{
    /**
     * Execute the tool and return a structured result.
     *
     * The collector receives live process output during execution.
     * Diagnostic messages (info, warnings, errors from the runner itself)
     * are returned in ToolRunResult::$messages.
     */
    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult;

    /**
     * Which tool name(s) this runner handles.
     *
     * @return list<string>
     */
    public function supportedTools(): array;
}
