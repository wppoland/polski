<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class SafetyInstructionsWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-safety-instructions';
    }

    public function get_title(): string
    {
        return __('Safety Instructions', 'polski');
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }

        $service = $this->container()->get(\Polski\Service\ProductInfoService::class);
        $instructions = $service->getSafetyInstructions($product);

        if ($instructions !== '') {
            printf(
                '<div class="polski-safety-instructions"><span class="polski-safety-instructions__label">%s:</span> %s</div>',
                esc_html__('Safety Instructions', 'polski'),
                esc_html($instructions),
            );
        }
    }
}
