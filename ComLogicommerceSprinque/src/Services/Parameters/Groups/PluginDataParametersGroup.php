<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceSprinque\Services\Parameters\Groups;

use SDK\Core\Services\Parameters\Groups\ParametersGroup;
use Plugins\ComLogicommerceSprinque\Services\Parameters\Validators\PluginDataPropertiesParametersValidator;

/**
 * This is the plugin data parameters group class.
 *
 * @see PluginDataParametersGroup::setAction()
 * @see PluginDataParametersGroup::getAction()
 * @see PluginDataParametersGroup::setData()
 * 
 * @see ParametersGroup
 * @see PluginDataPropertiesParametersValidator
 * 
 * @package Plugins\ComLogicommerceSprinque\Services\Parameters\Groups;
 */
class PluginDataParametersGroup extends ParametersGroup {

    protected string $action;

    protected array $data;

    /**
     * Sets the action parameter for this parameters group.
     *
     * @param string $action
     *
     * @return void
     */
    public function setAction(string $action): void {
        $this->action = $action;
    }

    /**
     * Returns the action value.
     *
     * @return string
     */
    public function getAction(): string {
        return $this->action;
    }

    /**
     * Sets the data parameter for this parameters group.
     *
     * @param array $data
     *
     * @return void
     */
    public function setData(array $data): void {
        $this->data = $data;
    }

    /**
     * @see ParametersGroup::getValidator()
     */
    protected function getValidator(): PluginDataPropertiesParametersValidator {
        return new PluginDataPropertiesParametersValidator();
    }
}
