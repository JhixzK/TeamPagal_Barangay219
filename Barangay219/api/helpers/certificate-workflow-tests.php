<?php
/**
 * Certificate Workflow Unit Tests
 * 
 * PHPUnit tests for certificate workflow validation, state transitions,
 * and business logic. Test data uses fixtures to simulate real scenarios.
 *
 * Run: phpunit certificate-workflow-tests.php
 *      OR use in test suite with: vendor/bin/phpunit
 */

// Mock dependencies if running standalone
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Import helpers (adjust paths based on your project structure)
require_once __DIR__ . '/certificate-validation.php';
require_once __DIR__ . '/certificate-notifications.php';
require_once __DIR__ . '/certificate-logging.php';

/**
 * Test Case: Certificate Validation
 */
class CertificateValidationTests {
    private $validator;
    private $passCount = 0;
    private $failCount = 0;

    public function __construct() {
        $this->validator = new CertificateValidator();
    }

    /**
     * Test: Valid submission data passes validation
     */
    public function testValidSubmissionPasses(): void {
        $validData = [
            'name' => 'Juan Dela Cruz',
            'age' => 35,
            'address' => 'Barangay 219, Tondo, Manila',
            'purpose_option' => 'Employment',
            'purpose_other' => ''
        ];

        $result = $this->validator->validateSubmission(1, $validData);
        $this->assertTrue($result->isValid(), 'Valid submission should pass');
    }

    /**
     * Test: Missing required fields fail validation
     */
    public function testMissingFieldsFail(): void {
        $invalidData = [
            'name' => '',  // Missing
            'age' => 0,    // Missing
            'address' => 'Valid Address',
            'purpose_option' => 'Employment'
        ];

        $result = $this->validator->validateSubmission(1, $invalidData);
        $this->assertFalse($result->isValid(), 'Missing fields should fail validation');
        $this->assertArrayHasKey('name', $result->getErrors(), 'Should have name error');
        $this->assertArrayHasKey('age', $result->getErrors(), 'Should have age error');
    }

    /**
     * Test: Age validation catches invalid values
     */
    public function testInvalidAgeFails(): void {
        $testCases = [
            ['age' => 0, 'expected' => false, 'desc' => 'Zero age'],
            ['age' => -5, 'expected' => false, 'desc' => 'Negative age'],
            ['age' => 200, 'expected' => false, 'desc' => 'Age > 150'],
            ['age' => 35, 'expected' => true, 'desc' => 'Valid age']
        ];

        foreach ($testCases as $case) {
            $data = [
                'name' => 'Valid Name',
                'age' => $case['age'],
                'address' => 'Valid Address',
                'purpose_option' => 'Employment'
            ];

            $result = $this->validator->validateSubmission(1, $data);
            $isValid = $result->isValid();
            
            if ($case['expected'] === $isValid) {
                $this->pass("Age validation ({$case['desc']}): PASS");
            } else {
                $this->fail("Age validation ({$case['desc']}): Expected " . 
                           ($case['expected'] ? 'valid' : 'invalid') . " but got " . 
                           ($isValid ? 'valid' : 'invalid'));
            }
        }
    }

    /**
     * Test: Purpose validation for "Others" option
     */
    public function testPurposeOthersValidation(): void {
        // Missing purpose_other when Others is selected
        $data = [
            'name' => 'Valid Name',
            'age' => 35,
            'address' => 'Valid Address',
            'purpose_option' => 'Others',
            'purpose_other' => ''  // Missing!
        ];

        $result = $this->validator->validateSubmission(1, $data);
        $this->assertFalse($result->isValid(), 'Others without details should fail');
        $this->assertArrayHasKey('purpose_other', $result->getErrors(), 'Should have purpose_other error');

        // Valid Others with details
        $data['purpose_other'] = 'Custom purpose details';
        $result = $this->validator->validateSubmission(1, $data);
        $this->assertTrue($result->isValid(), 'Others with details should pass');
    }

    /**
     * Test: State transition validation
     */
    public function testStateTransitionRules(): void {
        $validTransitions = [
            ['from' => 'pending', 'to' => 'approved', 'valid' => true],
            ['from' => 'pending', 'to' => 'rejected', 'valid' => true],
            ['from' => 'approved', 'to' => 'ready_for_pickup', 'valid' => true],
            ['from' => 'approved', 'to' => 'pending', 'valid' => true],  // Can revert for editing
            ['from' => 'ready_for_pickup', 'to' => 'released', 'valid' => true],
            ['from' => 'pending', 'to' => 'released', 'valid' => false],  // Invalid - must go through approved
            ['from' => 'rejected', 'to' => 'approved', 'valid' => false],  // Terminal state
            ['from' => 'released', 'to' => 'pending', 'valid' => false]    // Terminal state
        ];

        foreach ($validTransitions as $transition) {
            $result = $this->validator->validateTransition($transition['from'], $transition['to']);
            $isValid = $result->isValid();
            
            $status = ($transition['valid'] === $isValid) ? 'PASS' : 'FAIL';
            $desc = "{$transition['from']} → {$transition['to']} (expect " . 
                    ($transition['valid'] ? 'valid' : 'invalid') . ")";
            
            if ($status === 'PASS') {
                $this->pass("Transition: $desc");
            } else {
                $this->fail("Transition: $desc");
            }
        }
    }

    /**
     * Test: Name validation
     */
    public function testNameValidation(): void {
        $testCases = [
            ['name' => '', 'valid' => false, 'desc' => 'Empty name'],
            ['name' => 'AB', 'valid' => false, 'desc' => 'Too short'],
            ['name' => 'Juan Dela Cruz', 'valid' => true, 'desc' => 'Valid name'],
            ['name' => str_repeat('X', 256), 'valid' => false, 'desc' => 'Too long']
        ];

        foreach ($testCases as $case) {
            $data = [
                'name' => $case['name'],
                'age' => 35,
                'address' => 'Valid Address',
                'purpose_option' => 'Employment'
            ];

            $result = $this->validator->validateSubmission(1, $data);
            $isValid = $result->isValid();
            
            if ($case['valid'] === $isValid) {
                $this->pass("Name validation ({$case['desc']}): PASS");
            } else {
                $this->fail("Name validation ({$case['desc']}): Expected " . 
                           ($case['valid'] ? 'valid' : 'invalid'));
            }
        }
    }

    // Helper assertion methods
    private function assertTrue(bool $condition, string $message): void {
        if ($condition) {
            $this->pass($message);
        } else {
            $this->fail($message);
        }
    }

    private function assertFalse(bool $condition, string $message): void {
        if (!$condition) {
            $this->pass($message);
        } else {
            $this->fail($message);
        }
    }

    private function assertArrayHasKey(string $key, array $array, string $message): void {
        if (array_key_exists($key, $array)) {
            $this->pass($message);
        } else {
            $this->fail($message);
        }
    }

    private function pass(string $message): void {
        $this->passCount++;
        echo "[PASS] $message\n";
    }

    private function fail(string $message): void {
        $this->failCount++;
        echo "[FAIL] $message\n";
    }

    public function printSummary(): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Total: " . ($this->passCount + $this->failCount) . " | ";
        echo "Passed: {$this->passCount} | Failed: {$this->failCount}\n";
        echo str_repeat("=", 60) . "\n";
    }
}

/**
 * Test Case: Certificate Workflow State Machine
 */
class CertificateWorkflowStateMachineTests {
    private $passCount = 0;
    private $failCount = 0;

    /**
     * Test: Linear workflow progression
     * pending → approved → ready_for_pickup → released
     */
    public function testLinearWorkflowProgression(): void {
        // Simulate certificate states through workflow
        $workflow = [
            ['from' => null, 'to' => 'pending', 'desc' => 'Initial submission'],
            ['from' => 'pending', 'to' => 'approved', 'desc' => 'Admin approval'],
            ['from' => 'approved', 'to' => 'ready_for_pickup', 'desc' => 'Finalization'],
            ['from' => 'ready_for_pickup', 'to' => 'released', 'desc' => 'Release/Issue']
        ];

        foreach ($workflow as $step) {
            $validator = new CertificateValidator();
            $result = $validator->validateTransition($step['from'] ?? 'pending', $step['to']);
            
            if ($result->isValid()) {
                $this->pass("Workflow: {$step['desc']} ({$step['from']} → {$step['to']})");
            } else {
                $this->fail("Workflow: {$step['desc']} failed");
            }
        }
    }

    /**
     * Test: Rejection blocks progression
     * pending → rejected (terminal)
     */
    public function testRejectionTerminalState(): void {
        $validator = new CertificateValidator();
        
        // Can reject from pending ✓
        $result = $validator->validateTransition('pending', 'rejected');
        if ($result->isValid()) {
            $this->pass("Can reject from pending");
        } else {
            $this->fail("Should allow rejection from pending");
        }

        // Cannot progress from rejected ✓
        $result = $validator->validateTransition('rejected', 'approved');
        if (!$result->isValid()) {
            $this->pass("Cannot progress from rejected state");
        } else {
            $this->fail("Rejected should be terminal");
        }
    }

    /**
     * Test: Approved can revert to pending for re-editing
     */
    public function testApprovedRevertsForEditing(): void {
        $validator = new CertificateValidator();
        $result = $validator->validateTransition('approved', 'pending');
        
        if ($result->isValid()) {
            $this->pass("Can revert approved to pending for re-editing");
        } else {
            $this->fail("Should allow revert from approved to pending");
        }
    }

    /**
     * Test: Invalid shortcuts prevented
     */
    public function testInvalidShortcutsPrevented(): void {
        $validator = new CertificateValidator();
        
        // Trying to skip approval
        $shortcuts = [
            ['from' => 'pending', 'to' => 'ready_for_pickup', 'desc' => 'Skip approval'],
            ['from' => 'pending', 'to' => 'released', 'desc' => 'Skip to release'],
            ['from' => 'approved', 'to' => 'released', 'desc' => 'Skip finalization']
        ];

        foreach ($shortcuts as $shortcut) {
            $result = $validator->validateTransition($shortcut['from'], $shortcut['to']);
            if (!$result->isValid()) {
                $this->pass("Blocked invalid shortcut: {$shortcut['desc']}");
            } else {
                $this->fail("Should block: {$shortcut['desc']}");
            }
        }
    }

    private function pass(string $message): void {
        $this->passCount++;
        echo "[PASS] $message\n";
    }

    private function fail(string $message): void {
        $this->failCount++;
        echo "[FAIL] $message\n";
    }

    public function printSummary(): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Total: " . ($this->passCount + $this->failCount) . " | ";
        echo "Passed: {$this->passCount} | Failed: {$this->failCount}\n";
        echo str_repeat("=", 60) . "\n";
    }
}

/**
 * Test Case: Notification System
 */
class CertificateNotificationTests {
    private $passCount = 0;
    private $failCount = 0;

    /**
     * Test: All workflow states have appropriate notifications
     */
    public function testNotificationsCoverAllStates(): void {
        $states = ['submitted', 'approved', 'ready_for_pickup', 'rejected', 'released'];
        
        foreach ($states as $state) {
            // Verify notification method exists
            $methodName = "notify" . ucfirst(str_replace('_', '', $state));
            if (method_exists(new CertificateNotifier(), 'notify' . ucfirst(str_replace('_', '', str_replace('submitted', 'Submitted', str_replace('ready_for_pickup', 'ReadyForPickup', $state)))))) {
                $this->pass("Notification handler for: $state");
            } else {
                $this->fail("Missing notification for: $state");
            }
        }
    }

    private function pass(string $message): void {
        $this->passCount++;
        echo "[PASS] $message\n";
    }

    private function fail(string $message): void {
        $this->failCount++;
        echo "[FAIL] $message\n";
    }

    public function printSummary(): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Total: " . ($this->passCount + $this->failCount) . " | ";
        echo "Passed: {$this->passCount} | Failed: {$this->failCount}\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// ===========================================================================
// RUNNER - Execute tests
// ===========================================================================

if (php_sapi_name() === 'cli' || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING)) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "CERTIFICATE WORKFLOW TEST SUITE\n";
    echo str_repeat("=", 60) . "\n\n";

    // Run validation tests
    echo "1. CERTIFICATE VALIDATION TESTS\n";
    echo str_repeat("-", 60) . "\n";
    $validationTests = new CertificateValidationTests();
    $validationTests->testValidSubmissionPasses();
    $validationTests->testMissingFieldsFail();
    $validationTests->testInvalidAgeFails();
    $validationTests->testPurposeOthersValidation();
    $validationTests->testStateTransitionRules();
    $validationTests->testNameValidation();
    $validationTests->printSummary();

    // Run state machine tests
    echo "\n2. WORKFLOW STATE MACHINE TESTS\n";
    echo str_repeat("-", 60) . "\n";
    $stateMachineTests = new CertificateWorkflowStateMachineTests();
    $stateMachineTests->testLinearWorkflowProgression();
    $stateMachineTests->testRejectionTerminalState();
    $stateMachineTests->testApprovedRevertsForEditing();
    $stateMachineTests->testInvalidShortcutsPrevented();
    $stateMachineTests->printSummary();

    // Run notification tests
    echo "\n3. NOTIFICATION SYSTEM TESTS\n";
    echo str_repeat("-", 60) . "\n";
    $notificationTests = new CertificateNotificationTests();
    $notificationTests->testNotificationsCoverAllStates();
    $notificationTests->printSummary();

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TEST SUITE COMPLETE\n";
    echo str_repeat("=", 60) . "\n";
}

?>
