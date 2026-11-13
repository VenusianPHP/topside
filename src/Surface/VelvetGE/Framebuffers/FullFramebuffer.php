<?php

namespace Surface\VelvetGE\Framebuffers;


/** One whole frame. Keeps its contents after a present; its damage is always the whole surface. */
class FullFramebuffer extends StoreFramebuffer {}
