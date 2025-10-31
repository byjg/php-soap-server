<?php

namespace ByJG\SoapServer;

/* vim: set expandtab tabstop=4 shiftwidth=4: */

use ByJG\JinjaPhp\Loader\FileSystemLoader;
use DOMDocument;
use DOMElement;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use SoapServer;

/**
 * Easy Web Service (SOAP) creation
 *
 * PHP Version 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Services
 * @package   Webservice
 * @author    Manfred Weber <weber@mayflower.de>
 * @author    Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @copyright 2005 The PHP Group
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   CVS: $Id$
 * @link      http://dschini.org/Services/
 */

// {{{ abstract class Services_WebService

/**
 * PEAR::Services_Webservice
 *
 * The PEAR::Services_WebService class creates web services from your classes
 *
 * @category Services
 * @package  Webservices
 * @author   Manfred Weber <weber@mayflower.de>
 * @license  http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version  Release: @PACKAGE_VERSION@
 * @link     http://dschini.org/Services/
 */
class SoapHandler

{
    /**
     * Namespace of the webservice
     *
     * @var    string
     * @access public
     */
    public string $namespace;

    /**
     * Description of the webservice
     *
     * @var    string
     * @access public
     */
    public string $description;

    /**
     * Protocol of the webservice
     *
     * @var    string
     * @access public
     */
    public string $protocol;


    /**
     * SOAP-server options of the webservice
     *
     * @var    array
     * @access public
     */
    public array $soapServerOptions = array();

    /**
     * Array of SoapItem objects defining the service methods
     *
     * @var    array
     * @access private
     */
    private array $soapItems = array();

    /**
     * Magic method to handle SOAP calls when using SoapItems
     * This allows the SoapServer to call methods dynamically based on SoapItem executors
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        // Check if method exists in soapItems array
        if (!isset($this->soapItems[$name])) {
            throw new Exception("Method '$name' not found in SoapItems");
        }

        $soapItem = $this->soapItems[$name];

        // Call the executor with arguments as an associative array
        $argArray = [];
        $i = 0;
        foreach ($soapItem->args as $soapArg) {
            $value = $arguments[$i] ?? null;
            // Cast value to the correct type based on SoapParameterConfig definition
            if ($value !== null) {
                $value = $this->castParameterValue($value, $soapArg);
            }
            $argArray[$soapArg->name] = $value;
            $i++;
        }
        return call_user_func($soapItem->executor, $argArray);
    }

    /**
     * Get SoapItem by method name
     *
     * @param string $methodName
     * @return SoapOperationConfig|null
     */
    private function getSoapItem(string $methodName): SoapOperationConfig|null
    {
        return $this->soapItems[$methodName] ?? null;
    }

    /**
     * SOAP schema related URIs
     *
     * @access private
     */
    const SOAP_XML_SCHEMA_VERSION = 'http://www.w3.org/2001/XMLSchema';
    const SOAP_XML_SCHEMA_INSTANCE = 'http://www.w3.org/2001/XMLSchema-instance';
    const SOAP_SCHEMA_ENCODING = 'http://schemas.xmlsoap.org/soap/encoding/';
    const SOAP_XML_SCHEMA_MIME = 'http://schemas.xmlsoap.org/wsdl/mime/';
    const SOAP_ENVELOP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const SCHEMA_SOAP_HTTP = 'http://schemas.xmlsoap.org/soap/http';
    const SCHEMA_SOAP = 'http://schemas.xmlsoap.org/wsdl/soap/';
    const SCHEMA_WSDL = 'http://schemas.xmlsoap.org/wsdl/';
    const SCHEMA_WSDL_HTTP = 'http://schemas.xmlsoap.org/wsdl/http/';
    const SCHEMA_DISCO = 'http://schemas.xmlsoap.org/disco/';
    const SCHEMA_DISCO_SCL = 'http://schemas.xmlsoap.org/disco/scl/';
    const SCHEMA_DISCO_SOAP = 'http://schemas.xmlsoap.org/disco/soap/';

    /**
     * classes are parsed into struct
     *
     * @var    array
     * @access private
     */
    private array $wsdlStruct;

    /**
     * wsdl dom root node
     * the wsdl dom object
     *
     * @var    DOMDocument
     * @access private
     */
    private DOMDocument $wsdl;

    /**
     * wsdl-definitions dom node
     *
     * @var    DOMElement
     * @access private
     */
    private DOMElement $wsdlDefinitions;

    /**
     * Name of the class from which to create a webservice from
     *
     * @var    string
     * @access private
     */
    private string $classname;

    /**
     * error namespace
     *
     * @var    bool
     * @access private
     */
    private bool $warningNamespace;

    /**
     * constructor
     *
     * @param array  $soapItems   Array of SoapItem objects (required)
     * @param string $serviceName Service name for WSDL (defaults to class name if empty)
     * @param string $namespace   Namespace
     * @param string $description The description
     * @param array  $options     Options
     *
     * @access public
     * @return void
     */
    public function __construct(
        array $soapItems,
        string $serviceName = "",
        string $namespace = "",
        string $description = "",
        array $options = []
    )
    {
        // Set service name (used for WSDL generation)
        if (!empty($serviceName)) {
            $this->classname = $serviceName;
        } else {
            // Fallback to actual class name
            $this->classname = (new ReflectionObject($this))->getName();
        }

        if (isset($namespace) && $namespace != '') {
            $this->warningNamespace = false;
            //$namespace .= (substr($namespace, -1) == '/') ? '' : '/';
        } else {
            $this->warningNamespace = true;
            $namespace = 'http://example.org/';
        }
        $this->namespace   = $namespace;
        $this->description = ($description != '') ? $description : 'my example service description';
        $this->soapServerOptions = array_merge(
            [
                'uri' => $this->namespace,
                'encoding' => SOAP_ENCODED
            ],
            $options
        );
        $this->soapItems = $soapItems;
        $this->wsdlStruct = array();
        $this->protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
    }

    // }}}
    // {{{ handle()
    /**
     * handle
     *
     * @access public
     * @return void
     */
    public function handle(): void
    {
        $this->intoStruct();
        switch (strtolower($_SERVER['QUERY_STRING'] ?? '')){
        case 'wsdl':
            $this->handleWSDL();
            break;
        case 'disco':
            $this->handleDISCO();
            break;
        default:
            if (isset($_SERVER['HTTP_SOAPACTION'])) {
                $this->createServer();
            } elseif (isset($_REQUEST['httpmethod'])) {
               	$this->handleHTTP(); // by JG
            } else {
                $this->handleINFO();
            }
            break;
        }
    }

    // }}}
    // {{{ createServer()
    /**
     * create the soap-server
     *
     * @access private
     * @return void
     */
    private function createServer()
    {
        $server = new SoapServer(null, $this->soapServerOptions);
        $server->setObject($this);
        $server->handle();
    }

    // }}}
    // {{{ handleHTTP()
    /**
     * handle HTTP requests to the class (by JG)
     *
     * @access private
     */
    private function handleHTTP(): void
    {
        $methodName = $_REQUEST["httpmethod"];
        $soapItem = $this->getSoapItem($methodName);

        if (!$soapItem) {
            echo $this->_httpFailure . "Method does not exists";
            exit;
        }

        // Get parameters from SoapArg objects as associative array
        $paramValues = array();
        $missingParams = "";
        foreach ($soapItem->args as $soapArg) {
            $paramValue = isset($_REQUEST[$soapArg->name]) ? $_REQUEST[$soapArg->name] : null;
            // Only report missing if minOccurs > 0 (required)
            if (is_null($paramValue) && $soapArg->minOccurs > 0) {
                $missingParams .= (($missingParams == "") ? "" : ", ") . $soapArg->name;
            } elseif (!is_null($paramValue)) {
                // Cast value to the correct type
                $paramValues[$soapArg->name] = $this->castParameterValue($paramValue, $soapArg);
            }
        }

        if ($missingParams != "") {
            echo $this->_httpFailure . "Missing params $missingParams";
        } else {
            try {
                $result = call_user_func($soapItem->executor, $paramValues);

                if (is_array($result)) {
                    $str = sizeof($result);
                    foreach ($result as $line) {
                        $str .= "|$line";
                    }
                    echo $this->_httpSuccess . "$str";
                } elseif (is_object($result)) {
                    echo $this->_httpFailure . "Return type is not supported";
                } else {
                    echo $this->_httpSuccess . $result;
                }
            } catch (Exception $ex) {
                echo $this->_httpFailure . $ex->getMessage();
            }
        }
    }
    protected $_httpSuccess = "OK|";
    protected $_httpFailure = "ERR|";

    // }}}
    // {{{ handleWSDL()
    /**
     * handle wsdl
     *
     * @access private
     * @return void
     */
    private function handleWSDL()
    {
        header('Content-Type: text/xml');
        $this->wsdl = new DOMDocument('1.0', 'utf-8');
        $this->createWSDLDefinitions();
        $this->createWSDLTypes();
        $this->createWSDLMessages();
        $this->createWSDLPortType();
        $this->createWSDLBinding();
        $this->createWSDLService();
        echo $this->wsdl->saveXML();
    }

    // }}}
    // {{{ createDISCO()
    /**
     * handle disco
     *
     * @access private
     * @return void
     */
    private function handleDISCO(): void
    {
        header('Content-Type: text/xml');
        $disco = new DOMDocument('1.0', 'utf-8');
        $discoDiscovery = $disco->createElement('discovery');
        $discoDiscovery->setAttribute('xmlns:xsi', self::SOAP_XML_SCHEMA_INSTANCE);
        $discoDiscovery->setAttribute('xmlns:xsd', self::SOAP_XML_SCHEMA_VERSION);
        $discoDiscovery->setAttribute('xmlns', self::SCHEMA_DISCO);
        $discoContractRef = $disco->createElement('contractRef');
        $urlBase = $this->protocol . '://'
            . $_SERVER['HTTP_HOST']
            . $this->getSelfUrl();
        $discoContractRef->setAttribute('ref', $urlBase . '?wsdl');
        $discoContractRef->setAttribute('docRef', $urlBase);
        $discoContractRef->setAttribute('xmlns', self::SCHEMA_DISCO_SCL);
        $discoSoap = $disco->createElement('soap');
        $discoSoap->setAttribute('address', $urlBase);
        $discoSoap->setAttribute('xmlns:q1', $this->namespace);
        $discoSoap->setAttribute('binding', 'q1:' . $this->classname);
        $discoSoap->setAttribute('xmlns', self::SCHEMA_DISCO_SCL);
        $discoContractRef->appendChild($discoSoap);
        $discoDiscovery->appendChild($discoContractRef);
        $disco->appendChild($discoDiscovery);
        echo $disco->saveXML();
    }

    // }}}
    // {{{ handleINFO()
    /**
     * handle info-site
     *
     * @access private
     * @return void
     */
    private function handleINFO(): void
    {
        header('Content-Type: text/html');

        // Prepare methods data for template
        $methods = [];
        foreach ($this->wsdlStruct[$this->classname]['method'] as $methodName => $method) {
            $params = [];
            $paramTypesForSignature = [];

            foreach ($method['var'] as $methodVars) {
                if (isset($methodVars['param'])) {
                    $paramType = $methodVars['type'] . str_repeat('[]', $methodVars['length']);
                    $paramName = $methodVars['name'];

                    // Build params array for table display
                    $params[] = [
                        'name' => $paramName,
                        'type' => $paramType,
                        'required' => ($methodVars['minOccurs'] ?? 1) > 0 ? '✅ Yes' : '❌ No'
                    ];

                    // Build signature string
                    $paramTypesForSignature[] = $paramType . ' ' . $paramName;
                }
            }

            $returnTypes = [];
            foreach ($method['var'] as $methodVars) {
                if (isset($methodVars['return'])) {
                    $returnTypes[] = $methodVars['type']
                                     . str_repeat('[]', $methodVars['length']);
                }
            }

            $returnTypesStr = implode(', ', $returnTypes);
            $signatureStr = implode(', ', $paramTypesForSignature);

            // Generate example SOAP request
            $exampleRequest = $this->generateExampleRequest($methodName, $params);

            $methods[] = [
                'name' => $methodName,
                'returnTypesStr' => $returnTypesStr,
                'signatureStr' => $signatureStr,
                'description' => $method['description'] ?? '',
                'hasParams' => count($params) > 0,
                'params' => $params,
                'exampleRequest' => $exampleRequest
            ];
        }

        // Render template
        $templatePath = __DIR__ . '/../templates';
        $loader = new FileSystemLoader($templatePath);
        $template = $loader->getTemplate('service-info.html');

        echo $template->render([
            'classname' => $this->classname,
            'description' => $this->description,
            'selfUrl' => $this->getSelfUrl(),
            'methods' => $methods,
            'warningNamespace' => $this->warningNamespace === true || $this->namespace === 'http://example.org/'
        ]);
    }

    /**
     * Generate example SOAP request for a method
     *
     * @param string $methodName Method name
     * @param array $params Parameters array
     * @return string Example SOAP request XML
     */
    private function generateExampleRequest(string $methodName, array $params): string
    {
        $xml = '&lt;soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"&gt;' . "\n";
        $xml .= '  &lt;soap:Body&gt;' . "\n";
        $xml .= '    &lt;' . htmlspecialchars($methodName) . ' xmlns="' . htmlspecialchars($this->namespace) . '"&gt;' . "\n";

        foreach ($params as $param) {
            $xml .= '      &lt;' . htmlspecialchars($param['name']) . '&gt;...&lt;/' . htmlspecialchars($param['name']) . '&gt;' . "\n";
        }

        $xml .= '    &lt;/' . htmlspecialchars($methodName) . '&gt;' . "\n";
        $xml .= '  &lt;/soap:Body&gt;' . "\n";
        $xml .= '&lt;/soap:Envelope&gt;';

        return $xml;
    }

    private function getSelfUrl()
    {
	$url = $_SERVER['REQUEST_URI'] ?? '';

	$ipos = strpos($url, '?');
	if ($ipos !== false)
	{
		return substr($url, 0, $ipos);
	}
	else
	{
		return $url;
	}
    }

    // }}}
    // {{{ intoStruct()
    /**
     * parse SoapItems into struct
     *
     * @access private
     * @return void
     */
    protected function intoStruct()
    {
        $this->soapItemsIntoStruct();
        $this->classStructDispatch();
    }

    // }}}
    // {{{ classStructDispatch()
    /**
     * dispatch types
     *
     * @access private
     * @return void
     * @throws ReflectionException
     */
    protected function classStructDispatch(): void
    {
        foreach ($this->wsdlStruct[$this->classname]['method'] as $method) {
            foreach ($method['var'] as $var) {
                if (($var['class'] == 1 && $var['length'] == 0)
                    || ($var['class'] == 1 && $var['length'] > 0)
                ) {
                    $this->parseComplexTypeIntoStruct($var['type']);
                }
                if (($var['array'] == 1 && $var['length'] > 0)
                    || ($var['class'] == 1 && $var['length'] > 0)
                ) {
                    $typensSource = '';
                    for ($i = $var['length']; $i > 0; --$i) {
                        $typensSource .= 'ArrayOf';
                        $this->wsdlStruct['array'][$typensSource . $var['type']]
                            = substr(
                                $typensSource,
                                0,
                                strlen($typensSource) - 7
                            ) . $var['type'];
                    }
                }
            }
        }
    }

    // }}}
    // {{{ parseComplexTypeIntoStruct()
    /**
     * parse complex type (class) properties into struct for WSDL generation
     *
     * @param string $className string
     * @return void
     * @throws ReflectionException
     * @access private
     */
    protected function parseComplexTypeIntoStruct(string $className): void
    {
        if (!isset($this->wsdlStruct[$className])) {
            $class = new ReflectionClass($className);
            $properties = $class->getProperties();
            $this->wsdlStruct['class'][$className]['property'] = array();
            for ($i = 0; $i < count($properties); ++$i) {
                if ($properties[$i]->isPublic()) {
                    // Get type from property type (PHP 8.x typed properties)
                    $type = $properties[$i]->getType();

                    // Skip properties without type
                    if (!$type) {
                        continue;
                    }

                    // Get the type name
                    $typeName = $type->getName();

                    // Handle array types - check if it's an array
                    $length = 0;
                    if ($typeName === 'array') {
                        // For array types, we need to check docblock for the actual type
                        preg_match_all(
                            '~@var\s+(\S+)\[\]\s*$~m',
                            $properties[$i]->getDocComment(),
                            $var
                        );
                        if (isset($var[1][0])) {
                            $cleanType = $var[1][0];
                            $length = 1;
                        } else {
                            $cleanType = 'mixed';
                            $length = 1;
                        }
                    } else {
                        $cleanType = $typeName;
                    }

                    $typens = str_repeat('ArrayOf', $length);

                    $this->wsdlStruct['class'][$className]['property'][$properties[$i]->getName()]['type']
                        = $cleanType;
                    $this->wsdlStruct['class'][$className]['property'][$properties[$i]->getName()]['wsdltype']
                        = $typens.$cleanType;
                    $this->wsdlStruct['class'][$className]['property'][$properties[$i]->getName()]['length']
                        = $length;
                    $this->wsdlStruct['class'][$className]['property'][$properties[$i]->getName()]['array']
                        = $length > 0 && SoapType::tryFrom($cleanType) !== null;

                    // Check if it's a class (not a simple type)
                    $isObject = false;
                    if (SoapType::tryFrom($cleanType) === null) {
                        try {
                            new ReflectionClass($cleanType);
                            $isObject = true;
                        } catch (Exception $e) {
                            // Not a valid class, treat as simple type
                        }
                    }
                    $this->wsdlStruct['class'][$className]['property'][$properties[$i]->getName()]['class']
                        = $isObject;
                    if ($isObject) {
                        $this->parseComplexTypeIntoStruct($cleanType);
                    }
                    if ($length > 0) {
                        $typensSource = '';
                        for ($j = $length; $j > 0;  --$j) {
                            $typensSource .= 'ArrayOf';
                            $this->wsdlStruct['array'][$typensSource.$cleanType]
                                = substr(
                                    $typensSource,
                                    0,
                                    strlen($typensSource) - 7
                                )
                                . $cleanType;
                        }
                    }
                }
            }
        }
    }

    // }}}
    // {{{ soapItemsIntoStruct()
    /**
     * parse SoapItem array into struct
     *
     * @access protected
     * @return void
     */
    protected function soapItemsIntoStruct(): void
    {
        foreach ($this->soapItems as $methodName => $soapItem) {
            $this->wsdlStruct[$this->classname]['method'][$methodName]['description'] = $soapItem->description;

            $i = 0;
            // Process arguments/parameters (SoapArg objects)
            foreach ($soapItem->args as $soapArg) {
                // Check if type is SoapType enum (simple type) or string (complex type class)
                $isSimpleType = $soapArg->type instanceof SoapType;

                // Get type value (extract from enum if needed)
                $typeValue = $isSimpleType ? $soapArg->type->value : $soapArg->type;

                $cleanType = str_replace('[]', '', $typeValue, $length);
                $typens    = str_repeat('ArrayOf', $length);

                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['name'] = $soapArg->name;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['wsdltype'] = $typens . $cleanType;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['type'] = $cleanType;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['length'] = $length;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['array'] = $length > 0 && $isSimpleType;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['class'] = !$isSimpleType;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['param'] = true;

                // Store minOccurs and maxOccurs from SoapArg
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['minOccurs'] = $soapArg->minOccurs;
                $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['maxOccurs'] = $soapArg->maxOccurs;

                $i++;
            }

            // Process return type from SoapItem
            $isReturnSimpleType = $soapItem->returnType instanceof SoapType;
            $returnTypeValue = $isReturnSimpleType ? $soapItem->returnType->value : $soapItem->returnType;
            $cleanType = str_replace('[]', '', $returnTypeValue, $length);
            $typens = str_repeat('ArrayOf', $length);

            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['wsdltype'] = $typens.$cleanType;
            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['type'] = $cleanType;
            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['length'] = $length;
            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['array'] = $length > 0 && $cleanType != 'void' && $isReturnSimpleType;
            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['class'] = $cleanType != 'void' && !$isReturnSimpleType;
            $this->wsdlStruct[$this->classname]['method'][$methodName]['var'][$i]['return'] = true;
        }
    }

    // }}}
    /**
     * Create the definition node
     *
     * @return void
     */
    protected function createWSDLDefinitions(): void
    {
        /*
        <definitions name="myService"
            targetNamespace="urn:myService"
            xmlns:typens="urn:myService"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
            xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
            xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
            xmlns="http://schemas.xmlsoap.org/wsdl/">
        */

        $this->wsdlDefinitions = $this->wsdl->createElement('definitions');
        $this->wsdlDefinitions->setAttribute('name', $this->classname);
        $this->wsdlDefinitions->setAttribute(
            'targetNamespace',
            'urn:' . $this->classname
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns:typens',
            'urn:' . $this->classname
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns:xsd',
            self::SOAP_XML_SCHEMA_VERSION
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns:soap',
            self::SCHEMA_SOAP
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns:soapenc',
            self::SOAP_SCHEMA_ENCODING
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns:wsdl',
            self::SCHEMA_WSDL
        );
        $this->wsdlDefinitions->setAttribute(
            'xmlns',
            self::SCHEMA_WSDL
        );

        $this->wsdl->appendChild($this->wsdlDefinitions);
    }

    // }}}
    // {{{ createWSDL_types()
    /**
     * Create the types node
     *
     * @return void
     */
    protected function createWSDLTypes()
    {
        /*
        <types>
            <xsd:schema xmlns="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:myService"/>
        </types>
        */
        $types  = $this->wsdl->createElement('types');
        $schema = $this->wsdl->createElement('xsd:schema');
        $schema->setAttribute('xmlns', self::SOAP_XML_SCHEMA_VERSION);
        $schema->setAttribute('targetNamespace', 'urn:'.$this->classname);
        $types->appendChild($schema);

        // array
        /*
        <xsd:complexType name="ArrayOfclassC">
            <xsd:complexContent>
                <xsd:restriction base="soapenc:Array">
                    <xsd:attribute ref="soapenc:arrayType" wsdl:arrayType="typens:classC[]"/>
                </xsd:restriction>
            </xsd:complexContent>
        </xsd:complexType>
        */
        if (isset($this->wsdlStruct['array'])) {

            foreach ($this->wsdlStruct['array'] as $source => $target) {

                //<s:complexType name="ArrayOfArrayOfInt">
                //<s:sequence>
                //<s:element minOccurs="0" maxOccurs="unbounded" name="ArrayOfInt" nillable="true" type="tns:ArrayOfInt"/>
                //</s:sequence>

                $complexType    = $this->wsdl->createElement('xsd:complexType');
                $complexContent = $this->wsdl->createElement('xsd:complexContent');
                $restriction    = $this->wsdl->createElement('xsd:restriction');
                $attribute      = $this->wsdl->createElement('xsd:attribute');
                $restriction->appendChild($attribute);
                $complexContent->appendChild($restriction);
                $complexType->appendChild($complexContent);
                $schema->appendChild($complexType);

                $complexType->setAttribute('name', $source);
                $restriction->setAttribute('base', 'soapenc:Array');
                $attribute->setAttribute('ref', 'soapenc:arrayType');

                try {
                    $class = new ReflectionClass($target);
                } catch (Exception $e) {
                }

                if (SoapType::tryFrom($target) !== null) {
                    $attribute->setAttribute(
                        'wsdl:arrayType',
                        'xsd:'.$target.'[]'
                    );
                } elseif (isset($class)) {
                    $attribute->setAttribute(
                        'wsdl:arrayType',
                        'typens:'.$target.'[]'
                    );
                } else {
                    $attribute->setAttribute(
                        'wsdl:arrayType',
                        'typens:'.$target.'[]'
                    );
                }
                unset($class);

            }
        }

        // method parameter wrappers (for minOccurs/maxOccurs support)
        foreach ($this->wsdlStruct[$this->classname]['method'] as $methodName => $method) {
            // Create complexType for method parameters
            $complextype = $this->wsdl->createElement('xsd:complexType');
            $complextype->setAttribute('name', $methodName . 'Request');
            $sequence = $this->wsdl->createElement('xsd:sequence');
            $complextype->appendChild($sequence);

            foreach ($method['var'] as $var) {
                if (isset($var['param'])) {
                    $element = $this->wsdl->createElement('xsd:element');
                    $element->setAttribute('name', $var['name']);
                    $element->setAttribute(
                        'type',
                        (($var['array'] != 1 && $var['class'] != 1) ?
                        'xsd:' : 'typens:') . $var['wsdltype']
                    );

                    // Add minOccurs and maxOccurs
                    $element->setAttribute('minOccurs', (string)($var['minOccurs'] ?? 1));
                    $maxOccurs = $var['maxOccurs'] ?? 1;
                    $element->setAttribute('maxOccurs', $maxOccurs === -1 ? 'unbounded' : (string)$maxOccurs);

                    $sequence->appendChild($element);
                }
            }

            $schema->appendChild($complextype);
        }

        // class
        /*
        <xsd:complexType name="classB">
            <xsd:all>
                <xsd:element name="classCArray" type="typens:ArrayOfclassC" />
            </xsd:all>
        </xsd:complexType>
        */
        if (isset($this->wsdlStruct['class'])) {
            foreach ($this->wsdlStruct['class'] as $className=>$classProperty) {
                $complextype = $this->wsdl->createElement('xsd:complexType');
                $complextype->setAttribute('name', $className);
                $sequence = $this->wsdl->createElement('xsd:all');
                $complextype->appendChild($sequence);
                $schema->appendChild($complextype);
                foreach ($classProperty['property'] as $cpname => $cpValue) {
                    $element = $this->wsdl->createElement('xsd:element');
                    $element->setAttribute('name', $cpname);
                    $element->setAttribute(
                        'type',
                        (SoapType::tryFrom($cpValue['wsdltype']) !== null ? 'xsd:' : 'typens:') . $cpValue['wsdltype']
                    );

                    // Add minOccurs and maxOccurs if specified
                    if (isset($cpValue['minOccurs'])) {
                        $element->setAttribute('minOccurs', (string)$cpValue['minOccurs']);
                    }
                    if (isset($cpValue['maxOccurs'])) {
                        $maxOccurs = $cpValue['maxOccurs'];
                        $element->setAttribute('maxOccurs', $maxOccurs === -1 ? 'unbounded' : (string)$maxOccurs);
                    }

                    $sequence->appendChild($element);
                }
            }
        }
        $this->wsdlDefinitions->appendChild($types);
    }

    // }}}
    // {{{ createWSDL_messages()
    /**
     * Create the messages node
     *
     * @return void
     */
    protected function createWSDLMessages()
    {
        /*
        <message name="hello">
            <part name="i" type="xsd:int"/>
            <part name="j" type="xsd:string"/>
        </message>
        <message name="helloResponse">
            <part name="helloResponse" type="xsd:string"/>
        </message>
        */
        foreach ($this->wsdlStruct[$this->classname]['method'] as $name => $method) {
            $messageInput = $this->wsdl->createElement('message');
            $messageInput->setAttribute('name', $name);
            $messageOutput = $this->wsdl->createElement('message');
            $messageOutput->setAttribute('name', $name . 'Response');
            $this->wsdlDefinitions->appendChild($messageInput);
            $this->wsdlDefinitions->appendChild($messageOutput);

            foreach ($method['var'] as $methodVars) {
                if (isset($methodVars['param'])) {
                    $part = $this->wsdl->createElement('part');
                    $part->setAttribute('name', $methodVars['name']);
                    $part->setAttribute(
                        'type',
                        (($methodVars['array'] != 1 && $methodVars['class'] != 1) ?
                        'xsd:' : 'typens:') . $methodVars['wsdltype']
                    );
                    $messageInput->appendChild($part);
                }
                if (isset($methodVars['return'])) {
                    $part = $this->wsdl->createElement('part');
                    $part->setAttribute('name', $name.'Response');
                    $part->setAttribute(
                        'type',
                        (($methodVars['array'] != 1 && $methodVars['class'] != 1) ?
                        'xsd:' : 'typens:') . $methodVars['wsdltype']
                    );
                    $messageOutput->appendChild($part);
                }
            }
        }
    }

    // }}}
    // {{{ createWSDL_binding()
    /**
     * Create the binding node
     *
     * @return void
     */
    protected function createWSDLBinding()
    {
        /*
        <binding name="myServiceBinding" type="typens:myServicePort">
            <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
                <operation name="hello">
                    <soap:operation soapAction="urn:myServiceAction"/>
                    <input>
                        <soap:body use="encoded" namespace="urn:myService" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                    </input>
                    <output>
                        <soap:body use="encoded" namespace="urn:myService" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                    </output>
            </operation>
        </binding>
        */
        $binding = $this->wsdl->createElement('binding');
        $binding->setAttribute('name', $this->classname . 'Binding');
        $binding->setAttribute('type', 'typens:' . $this->classname . 'Port');
        $soap_binding = $this->wsdl->createElement('soap:binding');
        $soap_binding->setAttribute('style', 'rpc');
        $soap_binding->setAttribute('transport', self::SCHEMA_SOAP_HTTP);
        $binding->appendChild($soap_binding);
        foreach ($this->wsdlStruct[$this->classname]['method'] as $name => $vars) {
            $operation = $this->wsdl->createElement('operation');
            $operation->setAttribute('name', $name);
            $binding->appendChild($operation);
            $soap_operation = $this->wsdl->createElement('soap:operation');
            $soap_operation->setAttribute(
                'soapAction',
                'urn:'.$this->classname.'Action'
            );
            $operation->appendChild($soap_operation);
            $input  = $this->wsdl->createElement('input');
            $output = $this->wsdl->createElement('output');
            $operation->appendChild($input);
            $operation->appendChild($output);
            $soap_body = $this->wsdl->createElement('soap:body');
            $soap_body->setAttribute('use', 'encoded');
            $soap_body->setAttribute('namespace', 'urn:'.$this->namespace);
            $soap_body->setAttribute('encodingStyle', self::SOAP_SCHEMA_ENCODING);
            $input->appendChild($soap_body);
            $soap_body = $this->wsdl->createElement('soap:body');
            $soap_body->setAttribute('use', 'encoded');
            $soap_body->setAttribute('namespace', 'urn:'.$this->namespace);
            $soap_body->setAttribute('encodingStyle', self::SOAP_SCHEMA_ENCODING);
            $output->appendChild($soap_body);
        }
        $this->wsdlDefinitions->appendChild($binding);
    }

    // }}}
    // {{{ createWSDL_portType()
    /**
     * Create the portType node
     *
     * @return void
     */
    protected function createWSDLPortType()
    {
        /*
        <portType name="myServicePort">
            <operation name="hello">
                <input message="typens:hello"/>
                <output message="typens:helloResponse"/>
            </operation>
        </portType>
        */
        $portType = $this->wsdl->createElement('portType');
        $portType->setAttribute('name', $this->classname.'Port');
        foreach ($this->wsdlStruct[$this->classname]['method'] as $methodName => $methodVars) {
            $operation = $this->wsdl->createElement('operation');
            $operation->setAttribute('name', $methodName);
            $portType->appendChild($operation);

            $documentation = $this->wsdl->createElement('documentation');
            $documentation->appendChild(
                $this->wsdl->createTextNode($methodVars['description'])
            );
            $operation->appendChild($documentation);

            $input  = $this->wsdl->createElement('input');
            $output = $this->wsdl->createElement('output');
            $input->setAttribute('message', 'typens:' . $methodName);
            $output->setAttribute('message', 'typens:' . $methodName . 'Response');
            $operation->appendChild($input);
            $operation->appendChild($output);
        }
        $this->wsdlDefinitions->appendChild($portType);
    }

    // }}}
    // {{{ createWSDL_service()
    /**
     * Create the service node
     *
     * @return void
     */
    protected function createWSDLService()
    {
        /*
        <service name="myService">
        <port name="myServicePort" binding="typens:myServiceBinding">
        <soap:address location="http://dschini.org/test1.php"/>
        </port>
        </service>
        */
        $service = $this->wsdl->createElement('service');
        $service->setAttribute('name', $this->classname);
        $port = $this->wsdl->createElement('port');
        $port->setAttribute('name', $this->classname . 'Port');
        $port->setAttribute('binding', 'typens:' . $this->classname . 'Binding');
        $adress = $this->wsdl->createElement('soap:address');
        $adress->setAttribute(
            'location',
            $this->protocol . '://' . $_SERVER['HTTP_HOST'] . $this->getSelfUrl()
        );
        $port->appendChild($adress);
        $service->appendChild($port);
        $this->wsdlDefinitions->appendChild($service);
    }

    /**
     * Cast a parameter value to the correct type based on SoapParameterConfig definition
     *
     * @param mixed $value The value to cast
     * @param SoapParameterConfig $param The parameter definition
     * @return mixed The cast value
     * @throws Exception if the value cannot be cast to the target type
     */
    private function castParameterValue(mixed $value, SoapParameterConfig $param): mixed
    {
        // Cast based on SoapType enum
        if ($param->type instanceof SoapType) {
            return match($param->type) {
                SoapType::Integer => $this->castToInt($value),
                SoapType::Float, SoapType::Double => $this->castToFloat($value),
                SoapType::Boolean => $this->castToBoolean($value),
                SoapType::String => (string) $value,
                SoapType::ArrayOfInteger => array_map(fn($v) => $this->castToInt($v), (array) $value),
                SoapType::ArrayOfFloat, SoapType::ArrayOfDouble => array_map(fn($v) => $this->castToFloat($v), (array) $value),
                SoapType::ArrayOfBoolean => array_map(fn($v) => $this->castToBoolean($v), (array) $value),
                SoapType::ArrayOfString => array_map('strval', (array) $value),
                default => $value, // No casting for other types
            };
        }

        return $value;
    }

    /**
     * Cast a value to integer with validation
     *
     * @param mixed $value
     * @return int
     * @throws Exception
     */
    private function castToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new Exception(
            "Cannot cast value '" . var_export($value, true) . "' to integer"
        );
    }

    /**
     * Cast a value to float with validation
     *
     * @param mixed $value
     * @return float
     * @throws Exception
     */
    private function castToFloat(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new Exception(
            "Cannot cast value '" . var_export($value, true) . "' to float"
        );
    }

    /**
     * Cast a value to boolean
     *
     * @param mixed $value
     * @return bool
     */
    private function castToBoolean(mixed $value): bool
    {
        // Handle string representations of boolean
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'false' || $lower === '0' || $lower === '') {
                return false;
            }
            if ($lower === 'true' || $lower === '1') {
                return true;
            }
        }

        return (bool) $value;
    }
}

