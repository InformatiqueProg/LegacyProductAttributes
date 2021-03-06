<?php

namespace LegacyProductAttributes\Form;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\ProductQuery;

/**
 * Extension to the cart item add form.
 */
class CartAddFormExtension implements EventSubscriberInterface
{
    /**
     * Prefix for the legacy product attribute fields.
     */
    const LEGACY_PRODUCT_ATTRIBUTE_FIELD_PREFIX = 'legacy_product_attribute_';

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::FORM_AFTER_BUILD . '.thelia_cart_add' => ['cartFormAfterBuild', 128],
        ];
    }

    /**
     * Add fields for attribute values selection in our own way (since the product on has its default PSE, it has
     * no attributes as far as Thelia is concerned, but we want it to have all of its template's attributes).
     *
     * @param TheliaFormEvent $event
     */
    public function cartFormAfterBuild(TheliaFormEvent $event)
    {
        $sessionLocale = null;
        $session = $this->request->getSession();
        if ($session !== null) {
            $sessionLang = $session->getLang();
            if ($sessionLang !== null) {
                $sessionLocale = $sessionLang->getLocale();
            }
        }

        $product = ProductQuery::create()->findPk($this->request->getProductId());
        if ($product === null || $product->getTemplate() === null) {
            return;
        }

        $productAttributes = AttributeQuery::create()
            ->filterByTemplate($product->getTemplate())
            ->find();

        /** @var Attribute $productAttribute */
        foreach ($productAttributes as $productAttribute) {
            $attributeValues = AttributeAvQuery::create()
                ->findByAttributeId($productAttribute->getId());

            $choices = [];
            /** @var AttributeAv $attributeValue */
            foreach ($attributeValues as $attributeValue) {
                if ($sessionLocale !== null) {
                    $attributeValue->setLocale($sessionLocale);
                }
                $choices[$attributeValue->getId()] = $attributeValue->getTitle();
            }

            $event->getForm()->getFormBuilder()
                ->add(
                    static::LEGACY_PRODUCT_ATTRIBUTE_FIELD_PREFIX . $productAttribute->getId(),
                    'choice',
                    [
                        'choices' => $choices,
                        'required' => true,
                    ]
                );
        }
    }
}
