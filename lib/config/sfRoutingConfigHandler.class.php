<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfRoutingConfigHandler extends sfYamlConfigHandler
{
    /**
     * Executes this configuration handler.
     *
     * @param array $configFiles An array of absolute filesystem path to a configuration file
     *
     * @return string Data to be written to a cache file
     *
     * @throws sfConfigurationException If a requested configuration file does not exist or is not readable
     * @throws sfParseException         If a requested configuration file is improperly formatted
     */
    public function execute($configFiles)
    {
        $options = $this->getOptions();
        unset($options['cache']);

        $data = [];
        foreach ($this->parse($configFiles) as $name => $routeConfig) {
            $r = new ReflectionClass($routeConfig[0]);

            /** @var sfRoute $route */
            $route = $r->newInstanceArgs($routeConfig[1]);

            $routes = $route instanceof sfRouteCollection ? $route : [$name => $route];
            foreach (sfPatternRouting::flattenRoutes($routes) as $name => $route) {
                $route->setDefaultOptions($options);
                $data[] = sprintf('$this->routes[\'%s\'] = %s;', $name, var_export(serialize($route), true));
            }
        }

        return sprintf(
            "<?php\n".
                       "// auto-generated by sfRoutingConfigHandler\n".
                       "// date: %s\n%s\n",
            date('Y/m/d H:i:s'),
            implode("\n", $data)
        );
    }

    public function evaluate($configFiles)
    {
        $routeDefinitions = $this->parse($configFiles);

        $routes = [];
        foreach ($routeDefinitions as $name => $route) {
            $r = new ReflectionClass($route[0]);
            $routes[$name] = $r->newInstanceArgs($route[1]);
        }

        return $routes;
    }

    /**
     * @see sfConfigHandler
     */
    public static function getConfiguration(array $configFiles)
    {
        return static::parseYamls($configFiles);
    }

    protected function getOptions()
    {
        $config = sfFactoryConfigHandler::getConfiguration(sfContext::getInstance()->getConfiguration()->getConfigPaths('config/factories.yml'));

        return $config['routing']['param'];
    }

    protected function parse($configFiles)
    {
        // parse the yaml
        $config = static::getConfiguration($configFiles);

        // collect routes
        $routes = [];
        foreach ($config as $name => $params) {
            if (
                (isset($params['type']) && 'collection' == $params['type'])
                || (isset($params['class']) && false !== strpos($params['class'], 'Collection'))
            ) {
                $options = isset($params['options']) ? $params['options'] : [];
                $options['name'] = $name;
                $options['requirements'] = isset($params['requirements']) ? $params['requirements'] : [];

                $routes[$name] = [isset($params['class']) ? $params['class'] : 'sfRouteCollection', [$options]];
            } else {
                $routes[$name] = [isset($params['class']) ? $params['class'] : 'sfRoute', [
                    $params['url'] ?: '/',
                    isset($params['params']) ? $params['params'] : (isset($params['param']) ? $params['param'] : []),
                    isset($params['requirements']) ? $params['requirements'] : [],
                    isset($params['options']) ? $params['options'] : [],
                ]];
            }
        }

        return $routes;
    }
}
