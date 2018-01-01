<?php

namespace Victoire\Bundle\I18nBundle\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as BaseTranslator;
use Psr\Container\ContainerInterface;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Translator extends BaseTranslator
{
    protected $container;
    protected $options = [
        'cache_dir'             => 'test',
        'debug'                 => true,
        'resource_files'        => [],
        'domains'               => [],
        'default_kernel_locale' => 'en',
    ];
    protected $loaderIds;

    /**
     * @var MessageSelector
     */
    private $selector;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function __construct(RequestStack $requestStack, SessionInterface $session, ContainerInterface $container, MessageSelector $selector, $loaderIds = [], array $options = [])
    {
        $this->computeOptions($options);
        parent::__construct($container, $selector, $loaderIds, $options);
        $this->selector = $selector;
        $this->container = $container;
        $this->session = $session;
        $this->requestStack = $requestStack;
    }

    /**
     * @var array $options Options given as parameter for Symfony translator to compute with local option.
     * 
     * @return void
     */
    private function computeOptions(array &$options)
    {
        if (isset($options['domains'])) {
            $this->options['domains'] = $options['domains'];
            unset($options['domains']);
        }

        if (isset($options['domains'])) {
            $this->options['default_kernel_locale'] = $options['default_kernel_locale'];
            unset($options['default_kernel_locale']);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }
        if (null === $domain) {
            $domain = 'messages';
        } elseif (in_array($domain, $this->options['domains'])) {
            $locale = $this->getVictoireLocale();
        }
        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->catalogues[$locale]->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }
        if (null === $domain) {
            $domain = 'messages';
        } elseif (in_array($domain, $this->options['domains'])) {
            $locale = $this->getVictoireLocale();
        }
        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }
        $id = (string) $id;
        $catalogue = $this->catalogues[$locale];
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
    }

    /**
     * get the local in the session.
     */
    public function getLocale()
    {
        $this->locale = $this->getCurrentLocale();

        return $this->locale;
    }

    /**
     * get the locale of the administration template.
     *
     * @return string
     */
    public function getVictoireLocale()
    {
        $this->locale = $this->session->get('victoire_locale');

        return $this->locale;
    }

    /**
     * @return mixed|string
     */
    public function getCurrentLocale()
    {
        if ($request = $this->requestStack->getCurrentRequest()) {
            return $request->getLocale();
        }

        return $this->options['default_kernel_locale'];
    }
}
