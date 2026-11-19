# Log

## 2026-11-17

* `Feature::IMAGES`: Velvet answers true, the GPU engines false until they lend and upload. `Images::load()` takes a frame: refused past the header's count before any decode, cached per frame, passed to address and store decoders; an engine's own loader serves frame 0 only. [images](architecture/images.md)
* Velvet's surface lends its framebuffer to a decoder and records the closed region. It refuses a second open, and it does not decode a file itself. No `Feature` case for images until the GPU engines report it. [images](architecture/images.md)
* `Images::load($path, $engine)` returns an offscreen surface. `images.decoders` is the order, `auto` the registered decoders. Limits are checked from `info()` before any decode or native load. The cache key is the engine, the path, the file size and its modification time; `Images::release()` drops the surfaces, and an engine's `release()` does not, because Graphics does not depend on Images. `DrawingSurface::openForDecoder()`, `decoderStore()` and `closeDecoder()` are the lend and the return. `GraphicsEngine::loads()` is false until an engine overrides it. A frame with no address names the address decoder it refuses. No decoder is registered yet. [images](architecture/images.md)
* Images contracts: `ImageFormat::sniff` from leading bytes, `ImageSource` as a path or compressed bytes, `ImageInfo`, `ImageDecoder` (writes a `PixelMemory`) and `StoreImageDecoder` (writes a PHP store through its own calls), `ImageException` naming the format, reason, size, target layout or frame. [images](architecture/images.md)
