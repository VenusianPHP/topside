<?php

namespace Surface\VelvetGE\Drivers\Framebuffers;

use FbBuffer;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\PixelStore;
use Surface\VelvetGE\Framebuffers\CLand\CLandPixelStore;
use Surface\VelvetGE\Framebuffers\CLand\FbFormats;
use ValueError;

/** The five kinds with their bytes in C, through ext-fb. */
class CLandFramebufferDriver extends StoreFramebufferDriver
{
    /** The oldest ext-fb this driver runs on: 0.11.1 paints an image's tint, window and mask. */
    public const string MINIMUM = '0.11.1';

    /** The FbBuffer methods the driver calls that arrived after 0.11.1 was first built: an older build of the same version lacks them. */
    public const array REQUIRED_METHODS = ['paintGradient', 'filter'];

    /** The filter steps that arrived after 0.11.1 was first built, by the step list that asks for each. */
    public const array REQUIRED_STEPS = [
        'field' => ['field', 0, 0.0, 0.0, 1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, null],
        'table' => ['table', null, null, null, null, null, null],
    ];

    /** @var array<string, list<string>> */
    private static array $probed = [];

    /** Whether this PHP has an ext-fb of at least MINIMUM loaded, built with every method the driver calls. */
    public static function available(): bool
    {
        return self::loaded() && self::missingMethods() === [];
    }

    /** @return list<string> The REQUIRED_METHODS the class lacks, and the filter steps (as "filter(field)") its build does not know. */
    public static function missingMethods(string $class = FbBuffer::class): array
    {
        $missing = array_values(array_filter(self::REQUIRED_METHODS, fn (string $method): bool => ! method_exists($class, $method)));
        if ($missing === [] && $class === FbBuffer::class) {
            self::$probed[$class] ??= self::probeSteps($class);
            $missing = self::$probed[$class];
        }

        return $missing;
    }

    /** @throws FramebufferException When this PHP has no ext-fb, one older than MINIMUM, or a build of it that lacks what the driver calls. */
    public function __construct()
    {
        if (! self::loaded()) {
            throw FramebufferException::extensionMissing('fb', self::MINIMUM);
        }
        if (($missing = self::missingMethods()) !== []) {
            throw FramebufferException::extensionStale('fb', self::MINIMUM, $missing);
        }
    }

    public function driver(): string
    {
        return 'ext';
    }

    public function mint(FormatSpec $format, int $width, int $height): PixelStore
    {
        return new CLandPixelStore($format, $width, $height);
    }

    /** An ext-fb of at least MINIMUM is loaded. */
    private static function loaded(): bool
    {
        return class_exists(FbBuffer::class, false) && version_compare((string) phpversion('fb'), self::MINIMUM, '>=');
    }

    /** @return list<string> */
    private static function probeSteps(string $class): array
    {
        $missing = [];
        foreach (self::REQUIRED_STEPS as $name => $step) {
            try {
                $buffer = new $class(FbFormats::from(FormatSpec::rgba8()), 1, 1);
                $buffer->filter([$step], [], 0, 0, 1, 1);
            } catch (ValueError) {
                $missing[] = "filter({$name})";
            }
        }

        return $missing;
    }
}
