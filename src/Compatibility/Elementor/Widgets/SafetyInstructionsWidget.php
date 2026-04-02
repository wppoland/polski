<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class SafetyInstructionsWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-safety-instructions';
    }

    public function get_title(): string
    {
        return 'Instrukcje bezpieczeństwa';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }

        $service = $this->container()->get(\Spolszczony\Service\ProductInfoService::class);
        $instructions = $service->getSafetyInstructions($product);

        if ($instructions !== '') {
            printf(
                '<div class="spolszczony-safety-instructions"><span class="spolszczony-safety-instructions__label">%s:</span> %s</div>',
                esc_html__('Instrukcje bezpieczeństwa', 'spolszczony'),
                esc_html($instructions),
            );
        }
    }
}
