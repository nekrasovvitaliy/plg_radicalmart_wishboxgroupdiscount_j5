<?php
/**
 * @copyright   2013-2024 Nekrasov Vitaliy
 * @license     GNU General Public License version 2 or later
 */
namespace Joomla\Plugin\Radicalmart\Wishboxgroupdiscount\Extension;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use function defined;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * @since 1.0.0
 */
final class Wishboxgroupdiscount extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Autoload the language file.
	 *
	 * @var boolean
	 *
	 * @since 1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalMartPrepareProductPrice'          => 'onRadicalMartPrepareProductPrice',
			'onRadicalMartPreparePricesForm'            => 'onRadicalMartPreparePricesForm'
		];
	}

	/**
	 * Constructor.
	 *
	 * @param   DispatcherInterface  $dispatcher  The dispatcher
	 * @param   array                $config      An optional associative array of configuration settings
	 *
	 * @since   1.0.0
	 */
	public function __construct(DispatcherInterface $dispatcher, array $config)
	{
		parent::__construct($dispatcher, $config);
	}

	/**
	 * @param   Form          $pricesForm  Form
	 * @param   object|array  $data        Data
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onRadicalMartPreparePricesForm(Form $pricesForm, object|array $data): void
	{
		$pricesForm->load(
			'
<form addfieldprefix="Joomla\Component\RadicalMart\Administrator\Field">
	<fieldset name="prices_{currency_group}" label="{currency_title}">
		<fields name="prices">
			<fields name="{currency_group}">
				<field name="wishboxGroupDiscountUserGroupIds"
					type="UserGroupList"
					label="PLG_RADICALMART_WISHBOXGROUPDISCOUNT_USER_GROUPS"
					multiple="true"
					layout="joomla.form.field.list-fancy-select"
				/>
			</fields>
		</fields>
	</fieldset>
</form>'
		);
	}

	/**
	 * @param   string  $context   Context
	 * @param   array   $price     Price
	 * @param   array   $currency  Currency
	 *
	 * @return void
	 *
	 * @throws Exception
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onRadicalMartPrepareProductPrice(string $context, array &$price, array $currency): void
	{
		$app = Factory::getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		if (!isset($price['wishboxGroupDiscountUserGroupIds'])
			|| !is_array($price['wishboxGroupDiscountUserGroupIds'])
			|| !count($price['wishboxGroupDiscountUserGroupIds']))
		{
			return;
		}

		$priceUserGroupIds = $price['wishboxGroupDiscountUserGroupIds'];
		$user = $app->getIdentity();

		if ($user->id <= 0)
		{
			return;
		}

		$userGroupIds = $user->getAuthorisedGroups();

		$groupIds = array_uintersect($priceUserGroupIds, $userGroupIds, "strcasecmp");

		if (!count($groupIds))
		{
			return;
		}

		$discounts = [];

		$paramsUserGroupDiscounts = $this->params->get('userGroupDiscounts');

		foreach ($paramsUserGroupDiscounts as $paramUserGroupDiscount)
		{
			if (in_array($paramUserGroupDiscount->userGroupId, $groupIds))
			{
				$discounts[] = $paramUserGroupDiscount->discount;
			}
		}

		if (!count($discounts))
		{
			return;
		}

		$discount = max($discounts);

		$price['discount_enable'] = 1;
		$price['discount']        = $discount . '%';

		$price = PriceHelper::calculate($context, $price);

		// Prepare currency
		$price['currency'] = (empty($price['currency'])) ? PriceHelper::getDefaultCurrency()['code'] : $price['currency'];
		$currency          = PriceHelper::getCurrency($price['currency']);
		$code              = $currency['code'];

		// Prepare purchase
		$price['purchase']        = PriceHelper::clean($price['purchase'], $code);
		$price['purchase_enable'] = (int) $price['purchase_enable'];
		$price['purchase_string'] = PriceHelper::toString($price['purchase'], $code);
		$price['purchase_seo']    = PriceHelper::toString($price['purchase'], $code, 'seo');
		$price['purchase_number'] = PriceHelper::toString($price['purchase'], $code, false);

		// Prepare extra
		$price['extra'] = PriceHelper::cleanAdjustmentValue($price['extra']);

		if (!empty($price['extra']) && (strpos($price['extra'], '%') === false))
		{
			$price['extra']        = PriceHelper::clean($price['extra'], $code);
			$price['extra_string'] = PriceHelper::toString($price['extra'], $code);
			$price['extra_seo']    = PriceHelper::toString($price['extra'], $code, 'seo');
			$price['extra_number'] = PriceHelper::toString($price['extra'], $code, false);
		}
		elseif (!empty($price['extra']))
		{
			$price['extra_string'] = $price['extra'];
			$price['extra_seo']    = htmlspecialchars($price['extra']);
			$price['extra_number'] = null;
		}
		else
		{
			$price['extra_string'] = $price['extra'];
			$price['extra_seo']    = null;
			$price['extra_number'] = null;
		}

		// Prepare base
		$price['base']        = PriceHelper::clean($price['base'], $code);
		$price['base_string'] = PriceHelper::toString($price['base'], $code);
		$price['base_seo']    = PriceHelper::toString($price['base'], $code, 'seo');
		$price['base_number'] = PriceHelper::toString($price['base'], $code, false);

		// Prepare discount
		$price['discount']        = PriceHelper::cleanAdjustmentValue($price['discount']);
		$price['discount_enable'] = (int) $price['discount_enable'];

		if (!empty($price['discount']) && strpos($price['discount'], '%') === false)
		{
			$price['discount']        = PriceHelper::clean($price['discount'], $code);
			$price['discount_string'] = PriceHelper::toString($price['discount'], $code);
			$price['discount_seo']    = PriceHelper::toString($price['discount'], $code, 'seo');
			$price['discount_number'] = PriceHelper::toString($price['discount'], $code, false);
		}
		elseif (!empty($price['discount']))
		{
			$price['discount_string'] = $price['discount'];
			$price['discount_seo']    = htmlspecialchars($price['discount']);
			$price['discount_number'] = null;
		}
		else
		{
			$price['discount_string'] = $price['discount'];
			$price['discount_seo']    = null;
			$price['discount_number'] = null;
		}

		// Prepare discount_end
		if (!empty($price['discount_end']))
		{
			$price['discount_end'] = Factory::getDate($price['discount_end'])->toUnix();
		}

		$original = true;

		// Prepare final
		if (!$original && $price['discount_end'] > 0 && $price['discount_end'] <= Factory::getDate()->toUnix())
		{
			$price['discount_enable'] = 0;
			$price['final']           = $price['base'];
		}

		$price['final']        = PriceHelper::clean($price['final'], $code);
		$price['final_string'] = PriceHelper::toString($price['final'], $code);
		$price['final_seo']    = PriceHelper::toString($price['final'], $code, 'seo');
		$price['final_number'] = PriceHelper::toString($price['final'], $code, false);

		// Prepare benefit
		$price['benefit']        = PriceHelper::clean(($price['base'] - $price['final']), $code);
		$price['benefit_string'] = PriceHelper::toString($price['benefit'], $code);
		$price['benefit_seo']    = PriceHelper::toString($price['benefit'], $code, 'seo');
		$price['benefit_number'] = PriceHelper::toString($price['benefit'], $code, false);

		// Prepare sum base
		if (isset($price['sum_base']))
		{
			$price['sum_base']        = PriceHelper::clean($price['sum_base'], $code);
			$price['sum_base_string'] = PriceHelper::toString($price['sum_base'], $code);
			$price['sum_base_seo']    = PriceHelper::toString($price['sum_base'], $code, 'seo');
			$price['sum_base_number'] = PriceHelper::toString($price['sum_base'], $code, false);
		}

		// Prepare sum discount
		if (isset($price['sum_discount']))
		{
			$price['sum_discount'] = PriceHelper::cleanAdjustmentValue($price['sum_discount']);

			if (!empty($price['sum_discount']) && strpos($price['sum_discount'], '%') === false)
			{
				$price['sum_discount']        = PriceHelper::clean($price['sum_discount'], $code);
				$price['sum_discount_string'] = PriceHelper::toString($price['sum_discount'], $code);
				$price['sum_discount_seo']    = PriceHelper::toString($price['sum_discount'], $code, 'seo');
				$price['sum_discount_number'] = PriceHelper::toString($price['sum_discount'], $code, false);
			}
			elseif (!empty($price['sum_discount']))
			{
				$price['sum_discount_string'] = $price['sum_discount'];
				$price['sum_discount_seo']    = htmlspecialchars($price['sum_discount']);
				$price['sum_discount_number'] = null;
			}
			else
			{
				$price['sum_discount_string'] = $price['sum_discount'];
				$price['sum_discount_seo']    = null;
				$price['sum_discount_number'] = null;
			}
		}

		// Prepare sum final
		if (isset($price['sum_final']))
		{
			$price['sum_final']        = PriceHelper::clean($price['sum_final'], $code);
			$price['sum_final_string'] = PriceHelper::toString($price['sum_final'], $code);
			$price['sum_final_seo']    = PriceHelper::toString($price['sum_final'], $code, 'seo');
			$price['sum_final_number'] = PriceHelper::toString($price['sum_final'], $code, false);
		}

		// Prepare sum benefit
		if (isset($price['sum_base']) && isset($price['sum_final']))
		{
			$price['sum_benefit']        = PriceHelper::clean(($price['sum_base'] - $price['sum_final']), $code);
			$price['sum_benefit_string'] = PriceHelper::toString($price['sum_benefit'], $code);
			$price['sum_benefit_seo']    = PriceHelper::toString($price['sum_benefit'], $code, 'seo');
			$price['sum_benefit_number'] = PriceHelper::toString($price['sum_benefit'], $code, false);
		}
	}
}
