<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;

class DocumentationReleaseDispatchWorkflowTest extends TestCase
{
    public function testDocumentationReleaseDispatchCanRunManually(): void
    {
        $workflow = file_get_contents(PROJECT_ROOT . '/.github/workflows/documentation-release-dispatch.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertStringContainsString('version:', $workflow);
        $this->assertStringContainsString('release_url:', $workflow);
        $this->assertStringContainsString('release_body:', $workflow);
    }

    public function testDocumentationReleaseDispatchValidatesRequiredContext(): void
    {
        $workflow = file_get_contents(PROJECT_ROOT . '/.github/workflows/documentation-release-dispatch.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('DOCUMENTATION_REPO_TOKEN is missing', $workflow);
        $this->assertStringContainsString('Release tag \'$current_tag\' was not found', $workflow);
        $this->assertStringContainsString('event-type: framework-released', $workflow);
    }
}
