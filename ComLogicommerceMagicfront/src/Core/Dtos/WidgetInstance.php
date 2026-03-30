<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Dtos;

use SDK\Core\Dtos\Element;

/**
 * DTO for widget instances from Magic front API.
 * Maps the WidgetInstanceDTO structure from the Java backend.
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos
 */
class WidgetInstance extends Element {

    protected string $id = '';

    protected string $pageId = '';

    protected ?string $parentId = null;

    protected string $type = '';

    protected int $orderIndex = 0;

    protected int $createdAt = 0;

    protected int $updatedAt = 0;

    protected array $propertyValues = [];

    protected array $styleValues = [];

    protected array $children = [];

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? '';
        $this->pageId = $data['pageId'] ?? '';
        $this->parentId = $data['parentId'] ?? null;
        $this->type = $data['type'] ?? '';
        $this->orderIndex = (int)($data['orderIndex'] ?? 0);
        $this->createdAt = (int)($data['createdAt'] ?? 0);
        $this->updatedAt = (int)($data['updatedAt'] ?? 0);
        $this->propertyValues = $data['propertyValues'] ?? [];
        $this->styleValues = $data['styleValues'] ?? [];
        $this->children = $data['children'] ?? [];
    }

    public function getId(): string {
        return $this->id;
    }

    public function setId(string $id): void {
        $this->id = $id;
    }

    public function getPageId(): string {
        return $this->pageId;
    }

    public function setPageId(string $pageId): void {
        $this->pageId = $pageId;
    }

    public function getParentId(): ?string {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void {
        $this->parentId = $parentId;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): void {
        $this->type = $type;
    }

    public function getOrderIndex(): int {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): void {
        $this->orderIndex = $orderIndex;
    }

    public function getCreatedAt(): int {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): void {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): int {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): void {
        $this->updatedAt = $updatedAt;
    }

    public function getPropertyValues(): array {
        return $this->propertyValues;
    }

    public function setPropertyValues(array $propertyValues): void {
        $this->propertyValues = $propertyValues;
    }

    public function getStyleValues(): array {
        return $this->styleValues;
    }

    public function setStyleValues(array $styleValues): void {
        $this->styleValues = $styleValues;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function setChildren(array $children): void {
        $this->children = $children;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'pageId' => $this->pageId,
            'parentId' => $this->parentId,
            'type' => $this->type,
            'orderIndex' => $this->orderIndex,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'propertyValues' => $this->propertyValues,
            'styleValues' => $this->styleValues,
            'children' => $this->children,
        ];
    }
}
