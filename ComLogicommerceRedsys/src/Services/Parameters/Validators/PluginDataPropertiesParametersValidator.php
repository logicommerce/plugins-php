<?php declare(strict_types=1);

namespace Plugins\ComLogicommerceRedsys\Services\Parameters\Validators;

use SDK\Core\Services\Parameters\Validators\ParametersValidator;

/**
 * This is the plugin data parameters validation class.
 *
 * @package Plugins\ComLogicommerceRedsys\Services\Parameters\Validators
 */
class PluginDataPropertiesParametersValidator extends ParametersValidator {

    protected const REQUIRED_PARAMS = ['action'];

    protected string $action;

    protected array $data;

    protected function validateAction($action): ?bool {
        return $this->validateString($action);
    }

    protected function validateData($data): ?bool {
        return $this->validateArray($data);
    }

}
