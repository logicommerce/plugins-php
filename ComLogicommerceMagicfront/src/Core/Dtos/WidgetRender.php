<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Dtos;

use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

class WidgetRender extends Element {
    use ElementTrait;

    protected bool $success;
    protected string $messageError;
    protected string $html;
    protected string $widgetId;
    protected string $type;

    public function __construct(
        bool $success,
        string $html,
        string $widgetId,
        string $type,
        string $messageError,
    ) {
        $this->success = $success;
        $this->html = $html;
        $this->widgetId = $widgetId;
        $this->type = $type;
        $this->messageError = $messageError;
    }

    public function getSuccess(): bool {
        return $this->success;
    }

    public function setSuccess(bool $value): void {
        $this->success = $value;
    }

    public function getMessageError(): string {
        return $this->messageError;
    }

    public function setMessageError(string $value): void {
        $this->messageError = $value;
    }

    public function getHtml(): string {
        return $this->html;
    }

    public function setHtml(string $value): void {
        $this->html = $value;
    }

    public function getWidgetId(): string {
        return $this->widgetId;
    }

    public function setWidgetId(string $value): void {
        $this->widgetId = $value;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $value): void {
        $this->type = $value;
    }
}
