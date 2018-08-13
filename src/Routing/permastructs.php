<?php

namespace Metabolism\WordpressBundle;

use Metabolism\WordpressBundle\Helper\WordpressHelper as Wordpress;

use Symfony\Component\Routing\Route,
	Symfony\Component\Routing\RouteCollection;

global $wp_rewrite, $wp_post_types, $_config;

$controller_name = $_config->get('extra_permastructs.controller', 'MainController');
$_locale = ($_config->get('multisite.multilangue') && !$_config->get('multisite.subdomain_install')) ? '{_locale}{_separator}': '';

$collection = new RouteCollection();

$addRoute = function( $name, $struct, $paginate=false, $method='GET' ) use($controller_name, $_locale, $collection, $wp_rewrite)
{
	$name = str_replace('_structure', '', $name);

	$controller = 'App\Controller\\'.$controller_name.'::'.str_replace(' ', '',lcfirst(ucwords(str_replace('_', ' ', $name))).'Action');
	$path = str_replace('%/', '}/', str_replace('/%', '/{', $struct));
	$path = preg_replace('/\%$/', '}/', preg_replace('/^\%/', '/{', $path));
	$path = trim($path, '/');

	$route = new Route( $_locale.$path, ['_controller'=>$controller]);
	$collection->add('wp_'.$name, $route);

	if( $paginate )
	{
		$route = new Route( $_locale.$path.'/'.$wp_rewrite->pagination_base.'/{page}', ['_controller'=>$controller]);
		$route->setMethods($method);

		$collection->add('wp_'.$name.'_paged', $route);
	}
};

$addRoute('front', '');

foreach ($wp_rewrite->extra_permastructs as $name=>$permastruct)
{
	if( $permastruct['with_front'])
		$addRoute($name, $permastruct['struct'], $permastruct['paged']);
}

foreach ($wp_post_types as $wp_post_type)
{
	if( $wp_post_type->public && $wp_post_type->has_archive )
	{
		if( is_string($wp_post_type->has_archive) )
			$addRoute($wp_post_type->name.'_archive', $wp_post_type->has_archive, true);
		else
			$addRoute($wp_post_type->name.'_archive', $wp_post_type->query_var, true);
	}
}

$addRoute('author', $wp_rewrite->author_structure);
$addRoute('search', $wp_rewrite->search_structure, true);
$addRoute('search_post_type', $wp_rewrite->search_post_type_structure, true);
$addRoute('page', $wp_rewrite->page_structure);

if( !empty($_locale) )
{
	$locales = [];
	$sites = get_sites();

	foreach ($sites as $site)
		$locales[] = str_replace('/', '', $site->path);

	$collection->addRequirements(['_separator'=>'/?', '_locale'=>implode('|', $locales)]);
	$collection->addDefaults(['_separator'=>'/', '_locale'=>$_config->get('multisite.default_locale', 'en')]);
}

return $collection;

