<?php
class Hwguyguy_AjaxCart_UpdateController extends Mage_Core_Controller_Front_Action {
	/**
	 * Cart model in this controller.
	 *
	 * @var Mage_Checkout_Model_Cart $cart
	 */
	protected $cart = null;

	/**
	 * Update cart and return cart details in json.
	 */
	public function cartAction() {
		$result = $this->_updateShoppingCart();

		if ($result !== true) {
			$response = array('status' => 1, 'message' => $result);
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}

		$response = array('status' => 0);

		$quote = $this->_getCart()->getQuote();
		$checkoutHelper = Mage::helper('checkout');

		$items = array();
		foreach ($quote->getAllVisibleItems() as $item) {
			$items[] = array(
				'id' => $item->getId(),
				'qty' => $item->getQty(),
				'rowtotal' => $checkoutHelper->formatPrice($item->getRowTotal()),
			);
		}

		$response['items'] = $items;

		$totals = $quote->getTotals();
		if (isset($totals['subtotal'])) {
			$response['subtotal'] = $checkoutHelper->formatPrice($totals['subtotal']->getValue());
		}
		if (isset($totals['shipping'])) {
			$response['shipping'] = $checkoutHelper->formatPrice($totals['shipping']->getAddress()->getShippingAmount());
		}
		if (isset($totals['discount'])) {
			$response['discount'] = $checkoutHelper->formatPrice($totals['discount']->getValue());
		}
		if (isset($totals['grand_total'])) {
			$response['grand_total'] = $checkoutHelper->formatPrice($totals['grand_total']->getValue());
		}

		$response['version'] = strtotime($quote->getUpdatedAt());

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
	}

	/**
	 * Retrieve shopping cart model object
	 * @see Mage_Checkout_CartController
	 *
	 * @return Mage_Checkout_Model_Cart
	 */
	protected function _getCart()
	{
		if ($this->cart === null) {
			$this->cart = Mage::getSingleton('checkout/cart');
		}
		return $this->cart;
	}

	/**
	 * Get checkout session model instance
	 * @see Mage_Checkout_CartController
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('checkout/session');
	}

	/**
	 * Update customer's shopping cart
	 * @see Mage_Checkout_CartController
	 */
	protected function _updateShoppingCart() {
		try {
			$cartData = $this->getRequest()->getParam('cart');
			if (is_array($cartData)) {
				$filter = new Zend_Filter_LocalizedToNormalized(
					array('locale' => Mage::app()->getLocale()->getLocaleCode())
				);
				foreach ($cartData as $index => $data) {
					if (isset($data['qty'])) {
						$cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
					}
				}
				$cart = $this->_getCart();
				if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
					$cart->getQuote()->setCustomerId(null);
				}

				$cartData = $cart->suggestItemsQty($cartData);
				$cart->updateItems($cartData)
					->save();
			}
			$this->_getSession()->setCartWasUpdated(true);
			return true;
		} catch (Mage_Core_Exception $e) {
			//$this->_getSession()->addError(Mage::helper('core')->escapeHtml($e->getMessage()));
			return Mage::helper('core')->escapeHtml($e->getMessage());
		} catch (Exception $e) {
			//$this->_getSession()->addException($e, $this->__('Cannot update shopping cart.'));
			Mage::logException($e);
			return $this->__('Cannot update shopping cart.');
		}
	}
}
