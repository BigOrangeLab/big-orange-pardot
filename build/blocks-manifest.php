<?php
// This file is generated. Do not modify it manually.
return array(
	'big-orange-pardot' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/big-orange-pardot',
		'version' => '0.1.0',
		'title' => 'Big Orange Pardot',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'A WordPress Form block for WordPress to integrate with Pardot.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'attributes' => array(
			'pardotFormUrl' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'textdomain' => 'big-orange-pardot',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
