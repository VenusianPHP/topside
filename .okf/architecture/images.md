---
type: Module
title: Images
description: A PNG, JPEG or TIFF file becomes an offscreen DrawingSurface. Decoders are registered by other packages; this package ships none.
resource: src/Surface/Images/
tags: [topside, images, png, jpeg, tiff]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-17T10:30:00-05:00 }
sources:
  - id: format
    resource: src/Surface/Contracts/Images/ImageFormat.php
    title: ImageFormat
  - id: library
    resource: src/Surface/Images/ImageLibrary.php
    title: ImageLibrary
  - id: surface
    resource: src/Surface/Contracts/Graphics/DrawingSurface.php
    title: DrawingSurface
  - id: engine
    resource: src/Surface/Contracts/Graphics/GraphicsEngine.php
    title: GraphicsEngine
  - id: config
    resource: config/images.php
    title: images.php
---

# What a decoded image is

A decoded image is an offscreen `DrawingSurface`. `DrawingContext::image()` already draws one. `Images::load($path, $engine, $frame)` is the path from a file to that surface. No engine argument uses the default graphics engine, `app('gfx')->engine()`. `$frame` is a page of a multi-page TIFF, 0 otherwise; a frame past the header's count is refused before any decode, each frame is cached apart, and an engine's own loader is asked for frame 0 only, since `GraphicsEngine::load()` takes no frame.[^library]

Pixels cross into a frame as a `PixelMemory` from `DrawingSurface::openForDecoder()`. `closeDecoder($region)` records what was written: a CPU surface will record the region, a GPU surface will upload it. Null from `openForDecoder()` means the pixels live in PHP. `decoderStore()` is that framebuffer, and only a `StoreImageDecoder` may write it, through the store's own calls. An address decoder is never given that store, and a store decoder is never given a `PixelMemory`.[^surface]

`GraphicsEngine::loads()` is false on every engine shipped here. `load()` throws. An engine that decodes a format itself overrides both, and Images uses that surface instead of a decoder.[^engine]

# Who is tried

`config/images.php` `decoders` is the order. `auto` means every decoder a package registered, in registration order, at the place `auto` stands. The first that reads the format and writes the frame's layout is the one that decodes. A decoder whose constructor throws `ImageException` (its library is not loaded) is skipped. Any other throwable is a defect.[^config]

The header is that decoder's `info()`. Width, height, a side over `limits.side` or a pixel count over `limits.pixels` is refused before `decodeInto`, `decodeIntoStore`, or a native `load()`.

A frame that lends no `PixelMemory` refuses an address decoder by that decoder's name (`addressOnly`). A store decoder is the one that writes it, through the framebuffer's own calls. A C frame's `memory()` is the ticket, including a stride wider than a packed row, and `drewInto()` records the region the next `image` paint reads. Its first stored row is the top of that paint. A bottom-to-top frame emits its last stored row first when its own bytes are read back.

# Cache

The key is the engine, the path, the file's size and its modification time. The same unchanged file on the same engine is decoded once. A changed file releases the surface it replaces and decodes again. Two engines never share a surface.

Images owns the surfaces. Graphics does not depend on Images, and an engine's `release()` does not drop them. `Images::release()` does.[^library]

# What is not here yet

Decoders come from packages: Lustrous PHP and C (venusian-velvet), Blend2D, PlutoVG and ThorVG. Velvet's surface lends its framebuffer: a C frame's `memory()`, or null and the framebuffer itself when the pixels live in PHP. `closeDecoder()` records the given region on a C frame. Opening twice without closing is refused. Velvet does not decode a file itself. `Feature::IMAGES` says an engine's surfaces take a decode. Velvet answers true. Metal, Vulkan, OpenGL and SDL_GPU answer false until each implements the lend and the upload.

[^library]: ImageLibrary
[^surface]: DrawingSurface
[^engine]: GraphicsEngine
[^config]: images.php
