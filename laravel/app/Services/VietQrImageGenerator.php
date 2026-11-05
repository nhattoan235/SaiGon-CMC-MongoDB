<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Liopay\VietQR\Builder\QRIBFTBuilder;

class VietQrImageGenerator
{
    public function generate(string $bankBin, string $accountNo, string $amount, string $purpose): string
    {
        $payload = (new QRIBFTBuilder)
            ->setPointOfInitiation('12')
            ->setBeneficiaryBankBin($bankBin)
            ->setConsumerId($accountNo)
            ->setIBFTToAccount()
            ->setAmount($amount)
            ->setPurposeOfTransaction($purpose)
            ->build();

        $renderer = new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
