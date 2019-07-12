<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Asaplog extends CarrierModule
{
    protected $config_form = false;
    public $id_carrier;
    private $_moduleName = 'asaplog';
    private $debug = 'no';
    private $prazoEntrega = array();

    public function __construct()
    {
        $this->name = 'asaplog';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'ASAP Log';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->limited_countries = array('br');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'ASAP Log';
        $this->description = 'Entregas para lojas virtuais.';

        $this->confirmUninstall = 'Tem certeza que deseja desinstalar?';

        if (Configuration::get('ASAPLOG_DEBUG')) {
            $this->debug = Configuration::get('ASAPLOG_DEBUG');
        }
    }

    function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = 'O servidor de sua loja precisa da extensão cURL para PHP instalada e habiltiada.';
            return false;
        }

        if (!parent::install() or !$this->registerHook('displayBeforeCarrier')) {
            return false;
        }

        if (!Configuration::hasKey('ASAPLOG_TOKEN')) {
            Configuration::updateValue('ASAPLOG_TOKEN', '');
        }

        if (!Configuration::hasKey('ASAPLOG_DEBUG')) {
            Configuration::updateValue('ASAPLOG_DEBUG', 'no');
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() or !Configuration::deleteByName('ASAPLOG_TOKEN') or !Configuration::deleteByName('ASAPLOG_DEBUG') or !$this->unregisterHook('displayBeforeCarrier')) {
            return false;
        }
        return true;
    }

    public function installCarriers()
    {
        $token = Configuration::get('ASAPLOG_TOKEN');

        $config = array(
            'name' => 'ASAP Log',
            'id_tax_rules_group' => 0,
            'active' => true,
            'deleted' => 0,
            'shipping_handling' => false,
            'range_behavior' => 0,
            'delay' => array("br" => "Prazo de Entrega"),
            'id_zone' => 1,
            'is_module' => true,
            'shipping_external' => true,
            'asaplogToken' => $token,
            'carrierCode' => 'asaplog',
            'external_module_name' => $this->_moduleName,
            'need_range' => true
        );

        try {
            $this->installExternalCarrier($config, $this);
        } catch (\Exception $e) {
            $this->logMessage($e->getMessage());
        }
    }

    public static function installExternalCarrier($config, $asaplog)
    {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];
        $carrier->id_zone = $config['id_zone'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = $config['delay'];
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->asaplogToken = $config['asaplogToken'];
        $carrier->need_range = $config['need_range'];

        $languages = Language::getLanguages(true);

        foreach ($languages as $language) {
            $langId = (int)$language['id_lang'];
            $carrier->delay[$langId] = $config['delay']['br'];
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);

            foreach ($groups as $group) {
                try {
                    Db::getInstance()->insert('carrier_group', array('id_carrier' => (int)($carrier->id), 'id_group' => (int)($group['id_group'])));
                } catch (\Exception $e) {
                    $asaplog->logMessage($e->getMessage());
                }
            }

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '999999';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '999999';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->insert('carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])));
                Db::getInstance()->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'));
                Db::getInstance()->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'));
            }

            return (int)($carrier->id);
        }

        return false;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $debug = strval(Tools::getValue('ASAPLOG_DEBUG'));
            if (!$debug || empty($debug) || !Validate::isGenericName($debug)) {
                $output .= $this->displayError('Configuração "Registrar chamadas?" inválida');
            } else {
                Configuration::updateValue('ASAPLOG_DEBUG', $debug);
            }

            $token = strval(Tools::getValue('ASAPLOG_TOKEN'));
            if (!$token || empty($token) || !Validate::isGenericName($token)) {
                $output .= $this->displayError('Configuração "Chave de Integração" inválida');
            } else {
                Configuration::updateValue('ASAPLOG_TOKEN', $token);
                $this->installCarriers();
            }

            $output .= $this->displayConfirmation('Configurações atualizadas');
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $options = array(
            array(
                'id_option' => 'yes',
                'name' => 'Sim'
            ),
            array(
                'id_option' => 'no',
                'name' => 'Não'
            ),
        );

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => 'Configurar módulo ASAP Log',
                'subtitle'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Chave de Integração',
                    'name' => 'ASAPLOG_TOKEN',
                    'size' => 15,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => 'Registrar chamadas?',
                    'name' => 'ASAPLOG_DEBUG',
                    'required' => true,
                    'options' => array(
                        'query' => $options,
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                )
            ),
            'submit' => array(
                'title' => 'Salvar',
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => 'Salvar',
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => 'Voltar'
            )
        );
        $helper->fields_value['ASAPLOG_DEBUG'] = Configuration::get('ASAPLOG_DEBUG');
        $helper->fields_value['ASAPLOG_TOKEN'] = Configuration::get('ASAPLOG_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        $chave = Configuration::get('ASAPLOG_TOKEN');
        $cep = $this->obter_cep($params);

        $peso = 0;
        foreach ($params->getProducts() as $product) {
            $this->logMessage("Qtd: " . $product['quantity']);
            $this->logMessage("Peso: " . $product['weight']);

            if ($product['quantity'] > 0) {
                $peso = $product['weight'];
                if ($product['weight'] != null && $product['weight'] > 0) {
                    $peso += $product['quantity'] * $product['weight'];
                } else {
                    $this->logMessage("Produto sem peso: " . $product['name']);
                    return false;
                }
            }
        }

        if (isset($this->context->cart)) {
            $valor = $this->context->cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);
        } else {
            $cart = new cart($params->id);
            $valor = $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);
        }

        $this->logMessage("Chave: " . $chave);
        $this->logMessage("Destino: " . $cep);
        $this->logMessage("Peso: " . $peso);
        $this->logMessage("Valor: " . $valor);

        if ($chave == null || $chave == '') {
            $this->logMessage("Chave não cadastrada");
            $this->informarCotacaoInvalida(Configuration::get('PS_SHOP_NAME'), _PS_BASE_URL_);
            return false;
        }

        $cotacao = $this->fazerCotacao($peso, $valor, $cep, $chave);
        if ($cotacao != null) {
            if ($cotacao['preco'] != null && $cotacao['preco'] > 0) {
                $this->prazoEntrega[$this->id_carrier] = $cotacao['prazoMaximo'];
                return $cotacao['preco'] + $shipping_cost;
            } else {
                $this->logMessage("Preço nulo ou igual a zero");
                return false;
            }
        } else {
            $this->logMessage("Cotação nula");
            return false;
        }
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, 0);
    }

    public function formatarCep($cep)
    {
        $cep = trim($cep);
        $cep = preg_replace('/[^0-9\s]/', '', $cep);
        return $cep;
    }

    protected function obter_cep($params)
    {
        if ($this->context->customer->isLogged()) {
            $address = new Address($params->id_address_delivery);

            if ($address->postcode) {
                $cep = $address->postcode;
            }
        }

        if ($cep == null || $cep == '') {
            $address = new Address($params->id_address_delivery);

            if ($address->postcode) {
                $cep = $address->postcode;
            } else {
                return null;
            }
        }

        return $this->formatarCep($cep);
    }

    public function fazerCotacao($peso, $valor, $cep, $token)
    {
        try {
            $ch = curl_init();

            $url = 'https://app.asaplog.com.br/webservices/v1/consultarFrete?peso=' . $peso . '&valor=' . $valor . '&cep=' . $cep;
            $this->logMessage($url);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $headers = array();
            $headers[] = 'accessToken: ' . $token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                return null;
            }
            curl_close($ch);

            return json_decode($result, true);
        } catch (\Exception $exception) {
            $this->logMessage('Erro ao fazer cotação: ' . $exception->getMessage());
            return null;
        }
    }

    public function informarCotacaoInvalida($storeName, $baseUrl)
    {
        try {
            $ch = curl_init();

            $url = 'https://app.asaplog.com.br/webservices/v1/informarCotacaoInvalida?plataforma=MAGENTO&nome=' . $storeName . '&url=' . $baseUrl;
            $this->logMessage($url);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                return false;
            }
            curl_close($ch);

            return true;
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function logMessage($message)
    {
        if ($this->debug == 'yes') {
            $logger = new FileLogger(0);
            $logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/asaplog_cotacao.log");
            $logger->logDebug($message);
        }
    }

    public function hookDisplayBeforeCarrier($params)
    {
        if (null == $this->context->cart->getDeliveryOptionList()) {
            return;
        }

        $delivery_option_list = $this->context->cart->getDeliveryOptionList();

        foreach ($delivery_option_list as $id_address => $carrier_list_raw) {
            foreach ($carrier_list_raw as $key => $carrier_list) {
                foreach ($carrier_list['carrier_list'] as $id_carrier => $carrier) {

                    $msg = "Indisponível";

                    if (isset($this->prazoEntrega[$carrier['instance']->id])) {
                        $msg = 'Entrega em até ' . $this->prazoEntrega[$carrier['instance']->id] . ' dias úteis';
                    }

                    $carrier['instance']->delay[$this->context->cart->id_lang] = $msg;
                }
            }
        }
    }

}
