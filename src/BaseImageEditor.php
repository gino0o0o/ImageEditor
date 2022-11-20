<?php

namespace Gino0o0o\ImageEditor;

interface BaseImageEditor
{
	public function save(string $path) : bool;
	public function resize(int $width, int $height) : self;
}
