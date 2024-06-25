<?php
/**
 * @copyright 2013-2024 Nekrasov Vitaliy
 * @license GNU General Public License version 2 or later
 */
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Radicalmart\Wishboxgroupdiscount\Extension\Wishboxgroupdiscount;

defined('_JEXEC') or die;

return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$plugin = new Wishboxgroupdiscount(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('radicalmart', 'wishboxgroupdiscount')
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
