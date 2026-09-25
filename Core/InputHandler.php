<?php

namespace SPTK\Core;

/** Optional input contract for widgets that receive events while activated. */
interface InputHandler {

  public function handleInput(mixed $event): bool;

}
