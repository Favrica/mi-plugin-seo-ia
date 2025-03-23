<?php
// includes/Core/AIClientInterface.php
namespace MiPluginSEOIA\Core;

interface AIClientInterface {
    public function getKeywords($content);
    public function getTitle($content);
    public function getMetaDescription($content);
}