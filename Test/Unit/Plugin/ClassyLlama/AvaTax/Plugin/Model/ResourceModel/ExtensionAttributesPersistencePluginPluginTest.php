<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\ClassyLlama\AvaTax\Plugin\Model\ResourceModel;

use Bolt\Boltpay\Plugin\ClassyLlama\AvaTax\Plugin\Model\ResourceModel\ExtensionAttributesPersistencePluginPlugin;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use ClassyLlama\AvaTax\Plugin\Model\ResourceModel\ExtensionAttributesPersistencePlugin;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ClassyLlama\AvaTax\Plugin\Model\ResourceModel\ExtensionAttributesPersistencePluginPlugin
 */
class ExtensionAttributesPersistencePluginPluginTest extends BoltTestCase
{
    /** @var int Test order entity id */
    const ORDER_ID = 456;

    /** @var ExtensionAttributesPersistencePluginPlugin */
    protected $currentMock;

    /** @var ExtensionAttributesPersistencePlugin */
    protected $subjectMock;

    /** @var \Magento\Framework\DB\Adapter\AdapterInterface|MockObject */
    protected $connectionMock;

    protected function setUpInternal()
    {
        $this->currentMock = $this->getMockBuilder(ExtensionAttributesPersistencePluginPlugin::class)
            ->setMethods(null)
            ->getMock();
        $this->subjectMock = $this->getMockBuilder(ExtensionAttributesPersistencePlugin::class)
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->setMethods(['aroundSave'])
            ->getMock();
        $this->connectionMock = $this->getMockBuilder(\Magento\Framework\DB\Adapter\Pdo\Mysql::class)
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->setMethods(['select', 'from', 'where', 'getTable', 'fetchAll'])
            ->getMock();
    }

    /**
     * @return MockObject|\PHPUnit_Framework_MockObject_MockObject|\stdClass
     */
    private function createProceedMock()
    {
        return $this->getMockBuilder(\stdClass::class)
            ->setMethods(['proceed'])
            ->getMock();
    }

    /**
     * @test
     * that the plugin will not prevent the plugged method if the object saved (nextObject) is not an order
     */
    public function aroundAroundSave_nextObjectNotOrder_callsAndReturnsProceed()
    {
        $proceed = $this->createProceedMock();
        $nextObject = $this->createMock(Quote::class);
        $nextSubject = $this->createMock(\Magento\Framework\Model\ResourceModel\Db\AbstractDb::class);
        $nextProceed = $this->createProceedMock();
        $nextProceed->expects(static::never())->method('proceed');
        $proceed->expects(static::once())
            ->method('proceed')
            ->with($nextSubject, [$nextProceed, 'proceed'], $nextObject)
            ->willReturn($nextSubject);
        static::assertEquals(
            $nextSubject,
            $this->currentMock->aroundAroundSave(
                $this->subjectMock,
                [$proceed, 'proceed'],
                $nextSubject,
                [$nextProceed, 'proceed'],
                $nextObject
            )
        );
    }

    /**
     * @test
     * that the plugin will not prevent the plugged method if the object saved (nextObject) is an order
     * but there are no previous responses for it
     */
    public function aroundAroundSave_nextIsOrderButNoOldResponses_callsAndReturnsProceed()
    {
        $proceed = $this->createProceedMock();
        $nextObject = $this->createMock(Order::class);
        $nextSubject = $this->createMock(\Magento\Framework\Model\ResourceModel\Db\AbstractDb::class);
        $nextProceed = $this->createProceedMock();
        $nextProceed->expects(static::never())->method('proceed');
        $proceed->expects(static::once())->method('proceed')->with($nextSubject, [$nextProceed, 'proceed'], $nextObject)->willReturn($nextSubject);
        $nextSubject->method('getConnection')->willReturn($this->connectionMock);
        $nextSubject->method('getTable')->willReturnArgument(0);
        $nextObject->method('getId')->willReturn(self::ORDER_ID);

        $this->connectionMock->method('select')->willReturnSelf();
        $this->connectionMock->method('from')->willReturnSelf();
        $this->connectionMock->method('where')->with('order_id = ?', self::ORDER_ID)->willReturnSelf();
        $this->connectionMock->method('fetchAll')->willReturn([]);
        static::assertEquals(
            $nextSubject,
            $this->currentMock->aroundAroundSave(
                $this->subjectMock,
                [$proceed, 'proceed'],
                $nextSubject,
                [$nextProceed, 'proceed'],
                $nextObject
            )
        );
    }

    /**
     * @test
     * that the plugin will prevent the original plugin from executing if the object provided/saved is an order
     * and there are already one or more avatax responses associated with it
     * it will instead execute the next callback in line
     */
    public function aroundAroundSave_nextIsOrderWithOldResponses_callsNextCallbackInLine()
    {
        $proceed = $this->createProceedMock();
        $nextObject = $this->createMock(Order::class);
        $nextSubject = $this->createMock(\Magento\Framework\Model\ResourceModel\Db\AbstractDb::class);
        $nextProceed = $this->createProceedMock();
        $nextProceed->expects(static::once())->method('proceed')->with($nextObject)->willReturn($nextSubject);
        $proceed->expects(static::never())->method('proceed')->with($nextSubject, [$nextProceed, 'proceed'], $nextObject);
        $nextSubject->method('getConnection')->willReturn($this->connectionMock);
        $nextSubject->method('getTable')->willReturnArgument(0);
        $nextObject->method('getId')->willReturn(self::ORDER_ID);

        $this->connectionMock->method('select')->willReturnSelf();
        $this->connectionMock->method('from')->willReturnSelf();
        $this->connectionMock->method('where')->with('order_id = ?', self::ORDER_ID)->willReturnSelf();
        $this->connectionMock->method('fetchAll')->willReturn([
            ['id'=>1, 'order_id'=>self::ORDER_ID, 'avatax_response'=> '{"id":0,"code":"quote-514","company_id":0,"date":"2021-05-27","payment_date":"2021-05-27","status":"Temporary","type":"SalesOrder","batch_code":"","currency_code":"USD","exchange_rate_currency_code":"USD","customer_usage_type":"","entity_use_code":"","customer_vendor_code":"guest-514","customer_code":"guest-514","exempt_no":"","reconciled":false,"location_code":"","reporting_location_code":"","purchase_order_no":"","reference_code":"","salesperson_code":"","total_amount":39,"total_exempt":5,"total_discount":0,"total_tax":2.9,"total_taxable":34,"total_tax_calculated":2.9,"adjustment_reason":"NotAdjusted","locked":false,"version":1,"exchange_rate_effective_date":"2021-05-27","exchange_rate":1,"modified_date":"2021-05-27T20:55:38.5596409Z","modified_user_id":1081219,"tax_date":"2021-05-27T00:00:00","lines":[{"id":0,"transaction_id":0,"line_number":"1","customer_usage_type":"","entity_use_code":"","description":"Joust Duffle Bag","discount_amount":0,"exempt_amount":0,"exempt_cert_id":0,"exempt_no":"","is_item_taxable":true,"item_code":"24-MB01","line_amount":34,"quantity":1,"ref_1":"","ref_2":"","reporting_date":"2021-05-27","tax":2.9,"taxable_amount":34,"tax_calculated":2.9,"tax_code":"P0000000","tax_code_id":4316,"tax_date":"2021-05-27","tax_included":false,"details":[{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"06","juris_name":"CALIFORNIA","state_assigned_no":"","juris_type":"STA","jurisdiction_type":"State","non_taxable_amount":0,"rate":0.06,"tax":2.04,"taxable_amount":34,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA STATE TAX","tax_authority_type_id":45,"tax_calculated":2.04,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":34,"reporting_non_taxable_units":0,"reporting_exempt_units":0,"reporting_tax":2.04,"reporting_tax_calculated":2.04,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"037","juris_name":"LOS ANGELES","state_assigned_no":"","juris_type":"CTY","jurisdiction_type":"County","non_taxable_amount":0,"rate":0.0025,"tax":0.09,"taxable_amount":34,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA COUNTY TAX","tax_authority_type_id":45,"tax_calculated":0.09,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":34,"reporting_non_taxable_units":0,"reporting_exempt_units":0,"reporting_tax":0.09,"reporting_tax_calculated":0.09,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"EMBE0","juris_name":"SAN FRANCISCO COUNTY DISTRICT TAX SP","state_assigned_no":"052","juris_type":"STJ","jurisdiction_type":"Special","non_taxable_amount":0,"rate":0.0125,"tax":0.43,"taxable_amount":34,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA SPECIAL TAX","tax_authority_type_id":45,"tax_calculated":0.43,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":34,"reporting_non_taxable_units":0,"reporting_exempt_units":0,"reporting_tax":0.43,"reporting_tax_calculated":0.43,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"EMTC0","juris_name":"LOS ANGELES CO LOCAL TAX SL","state_assigned_no":"19","juris_type":"STJ","jurisdiction_type":"Special","non_taxable_amount":0,"rate":0.01,"tax":0.34,"taxable_amount":34,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA SPECIAL TAX","tax_authority_type_id":45,"tax_calculated":0.34,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":34,"reporting_non_taxable_units":0,"reporting_exempt_units":0,"reporting_tax":0.34,"reporting_tax_calculated":0.34,"liability_type":"Seller"}],"non_passthrough_details":[],"hs_code":"","cost_insurance_freight":0,"vat_code":"","vat_number_type_id":0},{"id":0,"transaction_id":0,"line_number":"2","customer_usage_type":"","entity_use_code":"","description":"Shipping costs","discount_amount":0,"exempt_amount":5,"exempt_cert_id":0,"exempt_no":"","is_item_taxable":false,"item_code":"Shipping","line_amount":5,"quantity":1,"ref_1":"","ref_2":"","reporting_date":"2021-05-27","tax":0,"taxable_amount":0,"tax_calculated":0,"tax_code":"FR020100","tax_code_id":4784,"tax_date":"2021-05-27","tax_included":false,"details":[{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"06","juris_name":"CALIFORNIA","state_assigned_no":"","juris_type":"STA","jurisdiction_type":"State","non_taxable_amount":5,"rate":0.06,"tax":0,"taxable_amount":0,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA STATE TAX","tax_authority_type_id":45,"tax_calculated":0,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":0,"reporting_non_taxable_units":5,"reporting_exempt_units":0,"reporting_tax":0,"reporting_tax_calculated":0,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"037","juris_name":"LOS ANGELES","state_assigned_no":"","juris_type":"CTY","jurisdiction_type":"County","non_taxable_amount":5,"rate":0.0025,"tax":0,"taxable_amount":0,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA COUNTY TAX","tax_authority_type_id":45,"tax_calculated":0,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":0,"reporting_non_taxable_units":5,"reporting_exempt_units":0,"reporting_tax":0,"reporting_tax_calculated":0,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"EMBE0","juris_name":"SAN FRANCISCO COUNTY DISTRICT TAX SP","state_assigned_no":"052","juris_type":"STJ","jurisdiction_type":"Special","non_taxable_amount":5,"rate":0.0125,"tax":0,"taxable_amount":0,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA SPECIAL TAX","tax_authority_type_id":45,"tax_calculated":0,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":0,"reporting_non_taxable_units":5,"reporting_exempt_units":0,"reporting_tax":0,"reporting_tax_calculated":0,"liability_type":"Seller"},{"id":0,"transaction_line_id":0,"transaction_id":0,"country":"US","region":"CA","exempt_amount":0,"juris_code":"EMTC0","juris_name":"LOS ANGELES CO LOCAL TAX SL","state_assigned_no":"19","juris_type":"STJ","jurisdiction_type":"Special","non_taxable_amount":5,"rate":0.01,"tax":0,"taxable_amount":0,"tax_type":"Sales","tax_sub_type_id":"S","tax_name":"CA SPECIAL TAX","tax_authority_type_id":45,"tax_calculated":0,"rate_type":"General","rate_type_code":"G","unit_of_basis":"PerCurrencyUnit","is_non_pass_thru":false,"is_fee":false,"reporting_taxable_units":0,"reporting_non_taxable_units":5,"reporting_exempt_units":0,"reporting_tax":0,"reporting_tax_calculated":0,"liability_type":"Seller"}],"non_passthrough_details":[],"hs_code":"","cost_insurance_freight":0,"vat_code":"","vat_number_type_id":0}],"addresses":[{"id":0,"transaction_id":0,"boundary_level":"Address","line_1":"1235 Howard St Ste D","line_2":"","line_3":"","city":"San Francisco","region":"CA","postal_code":"94103","country":"US","tax_region_id":4024330,"latitude":"37.775664","longitude":"-122.412153"},{"id":0,"transaction_id":0,"boundary_level":"Zip5","line_1":"","line_2":"","line_3":"","city":"","region":"CA","postal_code":"90034","country":"US","tax_region_id":4017056,"latitude":"34.027835","longitude":"-118.402675"}],"summary":[{"country":"US","region":"CA","juris_type":"State","juris_code":"06","juris_name":"CALIFORNIA","tax_authority_type":45,"state_assigned_no":"","tax_type":"Sales","tax_sub_type":"S","tax_name":"CA STATE TAX","rate_type":"General","taxable":34,"rate":0.06,"tax":2.04,"tax_calculated":2.04,"non_taxable":5,"exemption":0},{"country":"US","region":"CA","juris_type":"County","juris_code":"037","juris_name":"LOS ANGELES","tax_authority_type":45,"state_assigned_no":"","tax_type":"Sales","tax_sub_type":"S","tax_name":"CA COUNTY TAX","rate_type":"General","taxable":34,"rate":0.0025,"tax":0.09,"tax_calculated":0.09,"non_taxable":5,"exemption":0},{"country":"US","region":"CA","juris_type":"Special","juris_code":"EMTC0","juris_name":"LOS ANGELES CO LOCAL TAX SL","tax_authority_type":45,"state_assigned_no":"19","tax_type":"Sales","tax_sub_type":"S","tax_name":"CA SPECIAL TAX","rate_type":"General","taxable":34,"rate":0.01,"tax":0.34,"tax_calculated":0.34,"non_taxable":5,"exemption":0},{"country":"US","region":"CA","juris_type":"Special","juris_code":"EMBE0","juris_name":"SAN FRANCISCO COUNTY DISTRICT TAX SP","tax_authority_type":45,"state_assigned_no":"052","tax_type":"Sales","tax_sub_type":"S","tax_name":"CA SPECIAL TAX","rate_type":"General","taxable":34,"rate":0.0125,"tax":0.43,"tax_calculated":0.43,"non_taxable":5,"exemption":0}],"raw_result":{"id":0,"code":"quote-514","companyId":326994,"date":"2021-05-27","paymentDate":"2021-05-27","status":"Temporary","type":"SalesOrder","batchCode":"","currencyCode":"USD","exchangeRateCurrencyCode":"USD","customerUsageType":"","entityUseCode":"","customerVendorCode":"guest-514","customerCode":"guest-514","exemptNo":"","reconciled":false,"locationCode":"","reportingLocationCode":"","purchaseOrderNo":"","referenceCode":"","salespersonCode":"","totalAmount":39,"totalExempt":5,"totalDiscount":0,"totalTax":2.9,"totalTaxable":34,"totalTaxCalculated":2.9,"adjustmentReason":"NotAdjusted","locked":false,"version":1,"exchangeRateEffectiveDate":"2021-05-27","exchangeRate":1,"modifiedDate":"2021-05-27T20:55:38.5596409Z","modifiedUserId":1081219,"taxDate":"2021-05-27T00:00:00","lines":[{"id":0,"transactionId":0,"lineNumber":"1","customerUsageType":"","entityUseCode":"","description":"Joust Duffle Bag","discountAmount":0,"exemptAmount":0,"exemptCertId":0,"exemptNo":"","isItemTaxable":true,"itemCode":"24-MB01","lineAmount":34,"quantity":1,"ref1":"","ref2":"","reportingDate":"2021-05-27","tax":2.9,"taxableAmount":34,"taxCalculated":2.9,"taxCode":"P0000000","taxCodeId":4316,"taxDate":"2021-05-27","taxIncluded":false,"details":[{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"06","jurisName":"CALIFORNIA","stateAssignedNo":"","jurisType":"STA","jurisdictionType":"State","nonTaxableAmount":0,"rate":0.06,"tax":2.04,"taxableAmount":34,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA STATE TAX","taxAuthorityTypeId":45,"taxCalculated":2.04,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":34,"reportingNonTaxableUnits":0,"reportingExemptUnits":0,"reportingTax":2.04,"reportingTaxCalculated":2.04,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"037","jurisName":"LOS ANGELES","stateAssignedNo":"","jurisType":"CTY","jurisdictionType":"County","nonTaxableAmount":0,"rate":0.0025,"tax":0.09,"taxableAmount":34,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA COUNTY TAX","taxAuthorityTypeId":45,"taxCalculated":0.09,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":34,"reportingNonTaxableUnits":0,"reportingExemptUnits":0,"reportingTax":0.09,"reportingTaxCalculated":0.09,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"EMBE0","jurisName":"SAN FRANCISCO COUNTY DISTRICT TAX SP","stateAssignedNo":"052","jurisType":"STJ","jurisdictionType":"Special","nonTaxableAmount":0,"rate":0.0125,"tax":0.43,"taxableAmount":34,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA SPECIAL TAX","taxAuthorityTypeId":45,"taxCalculated":0.43,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":34,"reportingNonTaxableUnits":0,"reportingExemptUnits":0,"reportingTax":0.43,"reportingTaxCalculated":0.43,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"EMTC0","jurisName":"LOS ANGELES CO LOCAL TAX SL","stateAssignedNo":"19","jurisType":"STJ","jurisdictionType":"Special","nonTaxableAmount":0,"rate":0.01,"tax":0.34,"taxableAmount":34,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA SPECIAL TAX","taxAuthorityTypeId":45,"taxCalculated":0.34,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":34,"reportingNonTaxableUnits":0,"reportingExemptUnits":0,"reportingTax":0.34,"reportingTaxCalculated":0.34,"liabilityType":"Seller"}],"nonPassthroughDetails":[],"hsCode":"","costInsuranceFreight":0,"vatCode":"","vatNumberTypeId":0},{"id":0,"transactionId":0,"lineNumber":"2","customerUsageType":"","entityUseCode":"","description":"Shipping costs","discountAmount":0,"exemptAmount":5,"exemptCertId":0,"exemptNo":"","isItemTaxable":false,"itemCode":"Shipping","lineAmount":5,"quantity":1,"ref1":"","ref2":"","reportingDate":"2021-05-27","tax":0,"taxableAmount":0,"taxCalculated":0,"taxCode":"FR020100","taxCodeId":4784,"taxDate":"2021-05-27","taxIncluded":false,"details":[{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"06","jurisName":"CALIFORNIA","stateAssignedNo":"","jurisType":"STA","jurisdictionType":"State","nonTaxableAmount":5,"rate":0.06,"tax":0,"taxableAmount":0,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA STATE TAX","taxAuthorityTypeId":45,"taxCalculated":0,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":0,"reportingNonTaxableUnits":5,"reportingExemptUnits":0,"reportingTax":0,"reportingTaxCalculated":0,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"037","jurisName":"LOS ANGELES","stateAssignedNo":"","jurisType":"CTY","jurisdictionType":"County","nonTaxableAmount":5,"rate":0.0025,"tax":0,"taxableAmount":0,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA COUNTY TAX","taxAuthorityTypeId":45,"taxCalculated":0,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":0,"reportingNonTaxableUnits":5,"reportingExemptUnits":0,"reportingTax":0,"reportingTaxCalculated":0,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"EMBE0","jurisName":"SAN FRANCISCO COUNTY DISTRICT TAX SP","stateAssignedNo":"052","jurisType":"STJ","jurisdictionType":"Special","nonTaxableAmount":5,"rate":0.0125,"tax":0,"taxableAmount":0,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA SPECIAL TAX","taxAuthorityTypeId":45,"taxCalculated":0,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":0,"reportingNonTaxableUnits":5,"reportingExemptUnits":0,"reportingTax":0,"reportingTaxCalculated":0,"liabilityType":"Seller"},{"id":0,"transactionLineId":0,"transactionId":0,"country":"US","region":"CA","exemptAmount":0,"jurisCode":"EMTC0","jurisName":"LOS ANGELES CO LOCAL TAX SL","stateAssignedNo":"19","jurisType":"STJ","jurisdictionType":"Special","nonTaxableAmount":5,"rate":0.01,"tax":0,"taxableAmount":0,"taxType":"Sales","taxSubTypeId":"S","taxName":"CA SPECIAL TAX","taxAuthorityTypeId":45,"taxCalculated":0,"rateType":"General","rateTypeCode":"G","unitOfBasis":"PerCurrencyUnit","isNonPassThru":false,"isFee":false,"reportingTaxableUnits":0,"reportingNonTaxableUnits":5,"reportingExemptUnits":0,"reportingTax":0,"reportingTaxCalculated":0,"liabilityType":"Seller"}],"nonPassthroughDetails":[],"hsCode":"","costInsuranceFreight":0,"vatCode":"","vatNumberTypeId":0}],"addresses":[{"id":0,"transactionId":0,"boundaryLevel":"Address","line1":"1235 Howard St Ste D","line2":"","line3":"","city":"San Francisco","region":"CA","postalCode":"94103","country":"US","taxRegionId":4024330,"latitude":"37.775664","longitude":"-122.412153"},{"id":0,"transactionId":0,"boundaryLevel":"Zip5","line1":"","line2":"","line3":"","city":"","region":"CA","postalCode":"90034","country":"US","taxRegionId":4017056,"latitude":"34.027835","longitude":"-118.402675"}],"summary":[{"country":"US","region":"CA","jurisType":"State","jurisCode":"06","jurisName":"CALIFORNIA","taxAuthorityType":45,"stateAssignedNo":"","taxType":"Sales","taxSubType":"S","taxName":"CA STATE TAX","rateType":"General","taxable":34,"rate":0.06,"tax":2.04,"taxCalculated":2.04,"nonTaxable":5,"exemption":0},{"country":"US","region":"CA","jurisType":"County","jurisCode":"037","jurisName":"LOS ANGELES","taxAuthorityType":45,"stateAssignedNo":"","taxType":"Sales","taxSubType":"S","taxName":"CA COUNTY TAX","rateType":"General","taxable":34,"rate":0.0025,"tax":0.09,"taxCalculated":0.09,"nonTaxable":5,"exemption":0},{"country":"US","region":"CA","jurisType":"Special","jurisCode":"EMTC0","jurisName":"LOS ANGELES CO LOCAL TAX SL","taxAuthorityType":45,"stateAssignedNo":"19","taxType":"Sales","taxSubType":"S","taxName":"CA SPECIAL TAX","rateType":"General","taxable":34,"rate":0.01,"tax":0.34,"taxCalculated":0.34,"nonTaxable":5,"exemption":0},{"country":"US","region":"CA","jurisType":"Special","jurisCode":"EMBE0","jurisName":"SAN FRANCISCO COUNTY DISTRICT TAX SP","taxAuthorityType":45,"stateAssignedNo":"052","taxType":"Sales","taxSubType":"S","taxName":"CA SPECIAL TAX","rateType":"General","taxable":34,"rate":0.0125,"tax":0.43,"taxCalculated":0.43,"nonTaxable":5,"exemption":0}]},"raw_request":{"companyCode":"DEFAULT","customerCode":"guest-514","date":"2021-05-27","type":"0","lines":[{"number":1,"quantity":1,"amount":34,"taxCode":"","itemCode":"24-MB01","description":"Joust Duffle Bag","ref1":""},{"number":2,"quantity":1,"amount":5,"taxCode":"FR020100","itemCode":"Shipping","description":"Shipping costs","ref1":""}],"code":"quote-514","businessIdentificationNo":"","currencyCode":"USD","entityUseCode":"","exchangeRate":1,"exchangeRateEffectiveDate":"2021-05-27","reportingLocationCode":"","purchaseOrderNo":"","addresses":{"ShipTo":{"line1":"1235 Howard St Ste D","line2":"","line3":"","city":"San Francisco","region":"CA","postalCode":"94103","country":"US"},"ShipFrom":{"line1":"","line2":"","line3":null,"city":"","region":"CA","postalCode":"90034","country":"US"}}},"request":{"store_id":1,"commit":false,"currency_code":"USD","customer_code":"guest-514","entity_use_code":"","addresses":{"ShipTo":{"line_1":"1235 Howard St Ste D","line_2":"","line_3":"","city":"San Francisco","region":"CA","postal_code":"94103","country":"US"},"ShipFrom":{"line_1":"","line_2":"","city":"","postal_code":"90034","country":"US","region":"CA"}},"code":"quote-514","type":"0","exchange_rate":1,"exchange_rate_effective_date":"2021-05-27","lines":[{"mage_sequence_no":"sequence-1","item_code":"24-MB01","tax_code":"","description":"Joust Duffle Bag","quantity":1,"amount":34,"tax_included":false,"ref_1":"","ref_2":"","number":1},{"mage_sequence_no":"shipping","item_code":"Shipping","tax_code":"FR020100","description":"Shipping costs","quantity":1,"amount":5,"tax_included":false,"ref_1":"","ref_2":"","number":2}],"purchase_order_no":"","shipping_mode":"ground","business_identification_no":"","company_code":"DEFAULT","reporting_location_code":""}}']
        ]);
        static::assertEquals(
            $nextSubject,
            $this->currentMock->aroundAroundSave(
                $this->subjectMock,
                [$proceed, 'proceed'],
                $nextSubject,
                [$nextProceed, 'proceed'],
                $nextObject
            )
        );
    }
}
