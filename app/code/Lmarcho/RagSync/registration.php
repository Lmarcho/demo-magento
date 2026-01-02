<?php
/**
 * Lmarcho RagSync Module
 *
 * Synchronizes Magento 2 content with RAG backend for AI-powered search.
 *
 * @category  Lmarcho
 * @package   Lmarcho_RagSync
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Lmarcho_RagSync',
    __DIR__
);
