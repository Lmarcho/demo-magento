<?php
/**
 * Lmarcho RagSync Module - CmsPageBuilder Unit Tests
 *
 * Note: Tests that require complex Magento model mocking are in integration tests.
 * This file focuses on unit testing the document type detection logic.
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Unit\Model\DataBuilder;

use Lmarcho\RagSync\Model\DataBuilder\CmsPageBuilder;
use Lmarcho\RagSync\Model\Config;
use Magento\Cms\Api\PageRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CmsPageBuilderTest extends TestCase
{
    /**
     * @var CmsPageBuilder
     */
    private CmsPageBuilder $cmsPageBuilder;

    /**
     * @var PageRepositoryInterface|MockObject
     */
    private $pageRepositoryMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    protected function setUp(): void
    {
        $this->pageRepositoryMock = $this->createMock(PageRepositoryInterface::class);
        $this->configMock = $this->createMock(Config::class);

        $this->cmsPageBuilder = new CmsPageBuilder(
            $this->pageRepositoryMock,
            $this->configMock
        );
    }

    /**
     * @dataProvider documentTypeProvider
     */
    public function testDetectsCorrectDocumentType(string $identifier, string $expectedType): void
    {
        $result = $this->cmsPageBuilder->determineDocumentType($identifier);

        $this->assertEquals($expectedType, $result['type']);
    }

    public function documentTypeProvider(): array
    {
        return [
            // Privacy policy patterns
            ['privacy-policy', 'policy'],
            ['privacy', 'policy'],
            ['data-protection', 'policy'],
            ['data-protection-policy', 'policy'],

            // Terms patterns
            ['terms-and-conditions', 'policy'],
            ['terms-of-service', 'policy'],
            ['terms', 'policy'],
            ['conditions', 'policy'],

            // Return policy patterns
            ['return-policy', 'policy'],
            ['returns', 'policy'],
            ['refund-policy', 'policy'],
            ['refunds', 'policy'],

            // Warranty patterns
            ['warranty', 'policy'],
            ['warranty-information', 'policy'],
            ['guarantee', 'policy'],

            // Shipping patterns
            ['shipping-policy', 'shipping'],
            ['shipping-information', 'shipping'],
            ['delivery', 'shipping'],
            ['delivery-info', 'shipping'],

            // FAQ patterns
            ['faq', 'faq'],
            ['faqs', 'faq'],
            ['frequently-asked-questions', 'faq'],
            ['help', 'faq'],
            ['help-center', 'faq'],

            // Support patterns
            ['contact', 'support'],
            ['contact-us', 'support'],
            ['support', 'support'],
            ['customer-support', 'support'],
            ['reach-us', 'support'],

            // Guide patterns
            ['size-guide', 'guide'],
            ['fit-guide', 'guide'],
            ['how-to-order', 'guide'],
            ['buying-guide', 'guide'],
            ['tutorial', 'guide'],

            // About patterns
            ['about', 'general'],
            ['about-us', 'general'],
            ['our-story', 'general'],
            ['company', 'general'],

            // Default for unmatched
            ['random-page', 'general'],
            ['some-other-content', 'general'],
            ['home', 'general'],
        ];
    }

    public function testDetermineDocumentTypeReturnsSubtype(): void
    {
        // Test privacy policy subtype
        $result = $this->cmsPageBuilder->determineDocumentType('privacy-policy');
        $this->assertEquals('policy', $result['type']);
        $this->assertEquals('privacy_policy', $result['subtype']);

        // Test terms subtype
        $result = $this->cmsPageBuilder->determineDocumentType('terms-and-conditions');
        $this->assertEquals('policy', $result['type']);
        $this->assertEquals('terms', $result['subtype']);

        // Test contact subtype
        $result = $this->cmsPageBuilder->determineDocumentType('contact-us');
        $this->assertEquals('support', $result['type']);
        $this->assertEquals('contact', $result['subtype']);

        // Test about subtype
        $result = $this->cmsPageBuilder->determineDocumentType('about-us');
        $this->assertEquals('general', $result['type']);
        $this->assertEquals('about', $result['subtype']);
    }

    public function testDetermineDocumentTypeCaseInsensitive(): void
    {
        $result = $this->cmsPageBuilder->determineDocumentType('PRIVACY-POLICY');
        $this->assertEquals('policy', $result['type']);

        $result = $this->cmsPageBuilder->determineDocumentType('Privacy-Policy');
        $this->assertEquals('policy', $result['type']);

        $result = $this->cmsPageBuilder->determineDocumentType('FAQ');
        $this->assertEquals('faq', $result['type']);
    }
}
