<?php
/**
 * @copyright   2013-2024 Nekrasov Vitaliy
 * @license     GNU General Public License version 2 or later
 */
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Extension;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

return new class implements ServiceProviderInterface
{
	/**
	 * @param   Container  $container Container
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			InstallerScriptInterface::class,
			new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface
			{
				/**
				 * The application object
				 *
				 * @var  AdministratorApplication
				 *
				 * @since  1.0.0
				 */
				protected AdministratorApplication $app;

				/**
				 * The Database object.
				 *
				 * @var   DatabaseDriver
				 *
				 * @since  1.0.0
				 */
				protected DatabaseDriver $db;

				/**
				 * Minimum Joomla version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  1.0.0
				 */
				protected string $minimumJoomla = '4.4.2';

				/**
				 * Minimum PHP version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since 1.0.0
				 */
				protected string $minimumPhp = '8.1';

				/**
				 * Minimum Joomla version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  1.0.0
				 */
				protected string $minimumRadicalMart = '2.0.1';

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  $app  The application object.
				 *
				 * @since 1.0.0
				 */
				public function __construct(AdministratorApplication $app)
				{
					$this->app = $app;
					$this->db  = Factory::getContainer()->get('DatabaseDriver');
				}

				/**
				 * Function called after the extension is installed.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   1.0.0
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					$this->enablePlugin($adapter);

					return true;
				}

				/**
				 * Function called after the extension is updated.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   1.0.0
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called after the extension is uninstalled.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   1.0.0
				 */
				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called before extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   1.0.0
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					// Check compatible
					if (!$this->checkCompatible())
					{
						return false;
					}

					return true;
				}

				/**
				 * Function called after extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   1.0.0
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Method to check compatible.
				 *
				 * @throws  Exception
				 *
				 * @return  boolean True on success, False on failure.
				 *
				 * @since  1.0.0
				 */
				protected function checkCompatible(): bool
				{
					$app = Factory::getApplication();

					// Check PHP
					if (!(version_compare(PHP_VERSION, $this->minimumPhp) >= 0))
					{
						$app->enqueueMessage(
							Text::sprintf('PKG_JSHOPPING_WISHBOXLOCATION_ERROR_COMPATIBLE_PHP', $this->minimumPhp),
							'error'
						);

						return false;
					}

					// Check Joomla version
					if (!(new Version)->isCompatible($this->minimumJoomla))
					{
						$app->enqueueMessage(
							Text::sprintf('PKG_JSHOPPING_WISHBOXLOCATION_ERROR_COMPATIBLE_JOOMLA', $this->minimumJoomla),
							'error'
						);

						return false;
					}

					// Check RadicalMart
					if (!(version_compare($this->getRadicalMartVersion(), $this->minimumRadicalMart) >= 0))
					{
						$app->enqueueMessage(
							Text::sprintf('PKG_RADICALMART_WISHBOXATTRIBUTES_ERROR_COMPATIBLE_RADICALMART', $this->minimumRadicalMart),
							'error'
						);

						return false;
					}

					return true;
				}

				/**
				 * @return string
				 *
				 * @since 1.0.0
				 */
				protected function getRadicalMartVersion(): string
				{
					$component = ComponentHelper::getComponent('com_radicalmart');
					$extensionTable = new Extension(Factory::getContainer()->get('DatabaseDriver'));
					$extensionTable->load($component->id);
					$manifestCache = new Registry($extensionTable->manifest_cache); // phpcs:ignore

					return $manifestCache->get('version', '');
				}

				/**
				 * Enable plugin after installation.
				 *
				 * @param   InstallerAdapter  $adapter  Parent object calling object.
				 *
				 * @return void
				 *
				 * @since 1.0.0
				 */
				protected function enablePlugin(InstallerAdapter $adapter): void
				{
					// Prepare plugin object
					$plugin          = new stdClass;
					$plugin->type    = 'plugin';
					$plugin->element = $adapter->getElement();
					$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
					$plugin->enabled = 1;

					// Update record
					$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
				}
			}
		);
	}
};
