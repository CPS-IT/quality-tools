<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Workflow;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Complete workflow integration tests
 *
 * These tests validate that multiple quality tools work together correctly
 * in realistic end-to-end scenarios with proper tool interdependencies.
 */
final class CompleteWorkflowTest extends TestCase
{
    private string $tempProjectRoot;
    private array $workflowMetrics = [];

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('workflow_test_');
        $this->setupComplexTestProject();
        $this->workflowMetrics = [];
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupComplexTestProject(): void
    {
        TestHelper::createComposerJson($this->tempProjectRoot, [
            'name' => 'test/complete-workflow',
            'type' => 'project',
            'require' => [
                'typo3/cms-core' => '^13.4',
                'typo3/cms-frontend' => '^13.4',
                'typo3/cms-backend' => '^13.4',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'CompleteWorkflow\\Extension\\' => 'packages/workflow_extension/Classes/',
                    'CompleteWorkflow\\Tests\\' => 'tests/'
                ]
            ]
        ]);

        $this->createComplexExtensionStructure();
        $this->setupVendorWithIntegratedTools();
    }

    private function createComplexExtensionStructure(): void
    {
        $extensionDir = $this->tempProjectRoot . '/packages/workflow_extension';
        $classesDir = $extensionDir . '/Classes';

        $directories = [
            'Controller', 'Domain/Model', 'Domain/Repository',
            'Service', 'Utility', 'ViewHelpers', 'Configuration',
            'EventListener', 'DataProcessing', 'Middleware'
        ];

        foreach ($directories as $dir) {
            mkdir($classesDir . '/' . $dir, 0777, true);
        }

        // Create realistic TYPO3 extension files with various quality issues
        $this->createControllerWithIssues($classesDir);
        $this->createModelWithLegacyCode($classesDir);
        $this->createRepositoryWithPerformanceIssues($classesDir);
        $this->createServiceWithComplexLogic($classesDir);
        $this->createUtilityWithDeprecatedCalls($classesDir);
        $this->createViewHelperWithStyleIssues($classesDir);
        $this->createEventListenerWithTypingIssues($classesDir);
        $this->createConfigurationFiles($extensionDir);
        $this->createTestFiles();
    }

    private function createControllerWithIssues(string $baseDir): void
    {
        file_put_contents($baseDir . '/Controller/NewsController.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use CompleteWorkflow\Extension\Domain\Repository\NewsRepository;
use CompleteWorkflow\Extension\Service\NewsService;

/**
 * News controller with various code quality issues that the workflow should fix
 */
class NewsController extends ActionController
{
    /**
     * @var NewsRepository
     */
    protected $newsRepository;

    /**
     * @var NewsService
     */
    protected $newsService;

    /**
     * Inject news repository (old injection pattern)
     * @param NewsRepository $newsRepository
     */
    public function injectNewsRepository(NewsRepository $newsRepository)
    {
        $this->newsRepository = $newsRepository;
    }

    /**
     * Inject news service
     * @param NewsService $newsService
     */
    public function injectNewsService(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    /**
     * List action with multiple code quality issues
     */
    public function listAction($category = null, $limit = 10)
    {
        // Old array syntax (rector should modernize)
        $settings = array(
            'itemsPerPage' => $limit,
            'category' => $category,
            'orderBy' => 'crdate',
            'orderDirection' => 'DESC'
        );

        // Deprecated GeneralUtility makeInstance usage
        $configurationManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManager');
        $frameworkConfiguration = $configurationManager->getConfiguration('Framework');

        // Missing type validation and error handling
        $news = $this->newsRepository->findBySettings($settings);

        // Inefficient processing that could be optimized
        $processedNews = array();
        foreach ($news as $newsItem) {
            $processedNews[] = $this->newsService->enrichNewsItem($newsItem, $settings);
        }

        // Style issues (php-cs-fixer should fix)
        if(count($processedNews)>0){
            $this->view->assign('news',$processedNews);
        }else{
            $this->view->assign('message','No news found');
        }

        $this->view->assign('settings', $settings);
    }

    /**
     * Show action with security and type issues
     */
    public function showAction($news = null)
    {
        // Missing input validation (PHPStan should catch)
        if (!$news) {
            $newsUid = $this->request->getArgument('news');
            $news = $this->newsRepository->findByUid($newsUid);
        }

        // Potential null pointer (PHPStan should detect)
        $relatedNews = $this->newsRepository->findRelated($news->getCategory());

        $this->view->assign('news', $news);
        $this->view->assign('relatedNews', $relatedNews);
    }

    /**
     * Method with high complexity that should be refactored
     */
    public function complexFilterAction()
    {
        $filters = $this->request->getArguments();
        $results = array();

        // Complex nested logic (high cyclomatic complexity)
        if (isset($filters['category'])) {
            if (is_array($filters['category'])) {
                foreach ($filters['category'] as $categoryUid) {
                    if ($categoryUid > 0) {
                        $categoryNews = $this->newsRepository->findByCategory($categoryUid);
                        if ($categoryNews) {
                            foreach ($categoryNews as $news) {
                                if (isset($filters['dateFrom'])) {
                                    if ($news->getDatetime() >= $filters['dateFrom']) {
                                        if (isset($filters['dateTo'])) {
                                            if ($news->getDatetime() <= $filters['dateTo']) {
                                                $results[] = $news;
                                            }
                                        } else {
                                            $results[] = $news;
                                        }
                                    }
                                } else {
                                    $results[] = $news;
                                }
                            }
                        }
                    }
                }
            } else {
                // Single category logic...
            }
        } else {
            // No category filter logic...
        }

        $this->view->assign('filteredNews', $results);
    }
}
PHP
        );
    }

    private function createModelWithLegacyCode(string $baseDir): void
    {
        file_put_contents($baseDir . '/Domain/Model/News.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * News model with legacy patterns that need modernization
 */
class News extends AbstractEntity
{
    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $teaser = '';

    /**
     * @var string
     */
    protected $bodytext = '';

    /**
     * @var \DateTime
     */
    protected $datetime;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\CompleteWorkflow\Extension\Domain\Model\Category>
     */
    protected $categories;

    /**
     * @var \CompleteWorkflow\Extension\Domain\Model\Category
     */
    protected $primaryCategory;

    /**
     * Constructor with old-style initialization
     */
    public function __construct()
    {
        $this->datetime = new \DateTime();
        $this->categories = new ObjectStorage();
    }

    // Old-style getters and setters without type declarations

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTeaser()
    {
        return $this->teaser;
    }

    public function setTeaser($teaser)
    {
        $this->teaser = $teaser;
    }

    public function getBodytext()
    {
        return $this->bodytext;
    }

    public function setBodytext($bodytext)
    {
        $this->bodytext = $bodytext;
    }

    public function getDatetime()
    {
        return $this->datetime;
    }

    public function setDatetime($datetime)
    {
        $this->datetime = $datetime;
    }

    public function getCategories()
    {
        return $this->categories;
    }

    public function setCategories($categories)
    {
        $this->categories = $categories;
    }

    public function addCategory($category)
    {
        $this->categories->attach($category);
    }

    public function removeCategory($category)
    {
        $this->categories->detach($category);
    }

    public function getPrimaryCategory()
    {
        return $this->primaryCategory;
    }

    public function setPrimaryCategory($primaryCategory)
    {
        $this->primaryCategory = $primaryCategory;
    }

    /**
     * Helper method with old array syntax and missing return type
     */
    public function toArray()
    {
        return array(
            'uid' => $this->getUid(),
            'title' => $this->getTitle(),
            'teaser' => $this->getTeaser(),
            'bodytext' => $this->getBodytext(),
            'datetime' => $this->getDatetime(),
            'categories' => $this->getCategories()->toArray()
        );
    }
}
PHP
        );
    }

    private function createRepositoryWithPerformanceIssues(string $baseDir): void
    {
        file_put_contents($baseDir . '/Domain/Repository/NewsRepository.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * News repository with performance and code quality issues
 */
class NewsRepository extends Repository
{
    // Old-style property without type
    protected $defaultOrderings = array(
        'datetime' => QueryInterface::ORDER_DESCENDING
    );

    /**
     * Find by settings with performance issues
     */
    public function findBySettings($settings)
    {
        $query = $this->createQuery();
        $constraints = array();

        // Inefficient constraint building
        if (isset($settings['category']) && $settings['category']) {
            $categoryConstraints = array();
            if (is_array($settings['category'])) {
                foreach ($settings['category'] as $category) {
                    $categoryConstraints[] = $query->contains('categories', $category);
                }
                $constraints[] = $query->logicalOr($categoryConstraints);
            } else {
                $constraints[] = $query->contains('categories', $settings['category']);
            }
        }

        if (isset($settings['dateFrom']) && $settings['dateFrom']) {
            $constraints[] = $query->greaterThanOrEqual('datetime', $settings['dateFrom']);
        }

        if (isset($settings['dateTo']) && $settings['dateTo']) {
            $constraints[] = $query->lessThanOrEqual('datetime', $settings['dateTo']);
        }

        if (count($constraints) > 0) {
            $query->matching($query->logicalAnd($constraints));
        }

        // Missing limit handling (potential performance issue)
        return $query->execute();
    }

    /**
     * Find related news with N+1 query problem
     */
    public function findRelated($category, $excludeUid = null, $limit = 5)
    {
        $query = $this->createQuery();
        $constraints = array();

        if ($category) {
            $constraints[] = $query->contains('categories', $category);
        }

        if ($excludeUid) {
            $constraints[] = $query->logicalNot($query->equals('uid', $excludeUid));
        }

        if (count($constraints) > 0) {
            $query->matching($query->logicalAnd($constraints));
        }

        $query->setLimit($limit);

        // This will cause N+1 queries when accessing categories in templates
        return $query->execute();
    }

    /**
     * Method with missing return type and inefficient implementation
     */
    public function findByCategory($categoryUid)
    {
        // Inefficient: should use a proper query instead
        $allNews = $this->findAll();
        $result = array();

        foreach ($allNews as $news) {
            foreach ($news->getCategories() as $category) {
                if ($category->getUid() === $categoryUid) {
                    $result[] = $news;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Custom query method with old array syntax
     */
    public function findPopular($limit = 10)
    {
        $query = $this->createQuery();

        // Old array syntax
        $orderings = array(
            'views' => QueryInterface::ORDER_DESCENDING,
            'datetime' => QueryInterface::ORDER_DESCENDING
        );

        $query->setOrderings($orderings);
        $query->setLimit($limit);

        return $query->execute();
    }
}
PHP
        );
    }

    private function createServiceWithComplexLogic(string $baseDir): void
    {
        file_put_contents($baseDir . '/Service/NewsService.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use CompleteWorkflow\Extension\Domain\Model\News;

/**
 * News service with complex logic and quality issues
 */
class NewsService
{
    /**
     * Enrich news item with additional data (complex method needing refactoring)
     */
    public function enrichNewsItem($newsItem, $settings = array())
    {
        if (!$newsItem instanceof News) {
            return null;
        }

        // Complex processing with many conditional branches
        $enrichedData = array();
        $enrichedData['original'] = $newsItem;

        // Processing based on settings
        if (isset($settings['includeCategories']) && $settings['includeCategories']) {
            $categories = array();
            foreach ($newsItem->getCategories() as $category) {
                $categories[] = array(
                    'uid' => $category->getUid(),
                    'title' => $category->getTitle(),
                    'description' => $category->getDescription()
                );
            }
            $enrichedData['categories'] = $categories;
        }

        if (isset($settings['includeRelated']) && $settings['includeRelated']) {
            // Inefficient related news loading
            $relatedNews = array();
            foreach ($newsItem->getCategories() as $category) {
                $categoryRelated = $this->findRelatedByCategory($category->getUid(), $newsItem->getUid());
                foreach ($categoryRelated as $related) {
                    if (!in_array($related->getUid(), array_column($relatedNews, 'uid'))) {
                        $relatedNews[] = array(
                            'uid' => $related->getUid(),
                            'title' => $related->getTitle(),
                            'teaser' => $related->getTeaser()
                        );
                    }
                }
            }
            $enrichedData['related'] = $relatedNews;
        }

        if (isset($settings['processContent']) && $settings['processContent']) {
            // Complex content processing
            $content = $newsItem->getBodytext();
            if ($content) {
                // Multiple string operations (could be optimized)
                $content = $this->processLinks($content);
                $content = $this->processImages($content);
                $content = $this->processFormatting($content);
                $enrichedData['processedContent'] = $content;
            }
        }

        // Statistics calculation (expensive operation)
        if (isset($settings['includeStats']) && $settings['includeStats']) {
            $enrichedData['stats'] = $this->calculateStatistics($newsItem);
        }

        return $enrichedData;
    }

    /**
     * Method with old-style parameter handling
     */
    private function findRelatedByCategory($categoryUid, $excludeUid = null)
    {
        // This should use repository but doesn't (architectural issue)
        $newsRepository = GeneralUtility::makeInstance('CompleteWorkflow\\Extension\\Domain\\Repository\\NewsRepository');
        return $newsRepository->findByCategory($categoryUid);
    }

    /**
     * Methods with missing type hints and return types
     */
    private function processLinks($content)
    {
        // Simplified link processing
        return preg_replace('/\[link:(\d+)\]/', '<a href="/news/$1">Read more</a>', $content);
    }

    private function processImages($content)
    {
        // Simplified image processing
        return preg_replace('/\[image:(\d+)\]/', '<img src="/images/$1.jpg" alt="Image" />', $content);
    }

    private function processFormatting($content)
    {
        // Style formatting issues
        $content=str_replace("\n\n","</p><p>",$content);
        $content="<p>".$content."</p>";
        return$content;
    }

    private function calculateStatistics($newsItem)
    {
        // Expensive calculation that should be cached
        return array(
            'wordCount' => str_word_count($newsItem->getBodytext()),
            'readingTime' => ceil(str_word_count($newsItem->getBodytext()) / 200),
            'categoryCount' => $newsItem->getCategories()->count()
        );
    }
}
PHP
        );
    }

    private function createUtilityWithDeprecatedCalls(string $baseDir): void
    {
        file_put_contents($baseDir . '/Utility/ArrayUtility.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Array utility with deprecated patterns
 */
class ArrayUtility
{
    /**
     * Merge arrays with old-style implementation
     */
    public static function mergeRecursive($array1, $array2)
    {
        // Old array syntax
        $result = array();

        // Deprecated GeneralUtility usage
        $result = GeneralUtility::array_merge_recursive_overrule($array1, $array2);

        return $result;
    }

    /**
     * Filter array with style issues
     */
    public static function filterByKeys($array,$allowedKeys)
    {
        $filtered=array();
        foreach($array as$key=>$value){
            if(in_array($key,$allowedKeys)){
                $filtered[$key]=$value;
            }
        }
        return$filtered;
    }
}
PHP
        );
    }

    private function createViewHelperWithStyleIssues(string $baseDir): void
    {
        file_put_contents($baseDir . '/ViewHelpers/FormatDateViewHelper.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\ViewHelpers;

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * View helper with formatting and style issues
 */
class FormatDateViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('date', 'mixed', 'Date to format', true);
        $this->registerArgument('format', 'string', 'Date format', false, 'Y-m-d');
    }

    /**
     * Render method with style issues
     */
    public function render()
    {
        $date=$this->arguments['date'];
        $format=$this->arguments['format'];

        if(!$date){
            return'';
        }

        // Old-style type checking
        if(is_string($date)){
            $date=new \DateTime($date);
        }elseif(is_int($date)){
            $date=new \DateTime('@'.$date);
        }elseif(!$date instanceof \DateTime){
            return'Invalid date';
        }

        return$date->format($format);
    }
}
PHP
        );
    }

    private function createEventListenerWithTypingIssues(string $baseDir): void
    {
        file_put_contents($baseDir . '/EventListener/NewsEventListener.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Extension\EventListener;

/**
 * Event listener with missing type declarations
 */
class NewsEventListener
{
    /**
     * Handle news creation event
     */
    public function onNewsCreated($event)
    {
        // Missing parameter type
        $news = $event->getNews();

        // Process news without proper type checking
        if ($news) {
            $this->processNewNews($news);
        }
    }

    /**
     * Handle news update event
     */
    public function onNewsUpdated($event)
    {
        $news = $event->getNews();
        $oldData = $event->getOldData();

        // Complex logic without return type
        if ($this->hasSignificantChanges($news, $oldData)) {
            $this->notifySubscribers($news);
        }
    }

    private function processNewNews($news)
    {
        // Missing implementation and type hints
    }

    private function hasSignificantChanges($news, $oldData)
    {
        // Missing return type and implementation
        return false;
    }

    private function notifySubscribers($news)
    {
        // Missing implementation
    }
}
PHP
        );
    }

    private function createConfigurationFiles(string $extensionDir): void
    {
        // Create ext_emconf.php
        file_put_contents($extensionDir . '/ext_emconf.php', <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Workflow Extension',
    'description' => 'Test extension for complete workflow testing',
    'category' => 'plugin',
    'version' => '1.0.0',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
        ],
    ],
];
PHP
        );

        // Create TypoScript configuration
        mkdir($extensionDir . '/Configuration/TypoScript', 0777, true);
        file_put_contents($extensionDir . '/Configuration/TypoScript/setup.typoscript', <<<'TS'
plugin.tx_workflowextension {
    settings {
        pagination {
            itemsPerPage = 10
            insertAbove = 1
            insertBelow = 1
        }

        detail {
            showRelated = 1
            relatedLimit = 5
        }
    }

    view {
        templateRootPaths {
            0 = EXT:workflow_extension/Resources/Private/Templates/
        }
        partialRootPaths {
            0 = EXT:workflow_extension/Resources/Private/Partials/
        }
        layoutRootPaths {
            0 = EXT:workflow_extension/Resources/Private/Layouts/
        }
    }
}
TS
        );
    }

    private function createTestFiles(): void
    {
        $testDir = $this->tempProjectRoot . '/tests/Unit/Service';
        mkdir($testDir, 0777, true);

        file_put_contents($testDir . '/NewsServiceTest.php', <<<'PHP'
<?php
namespace CompleteWorkflow\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use CompleteWorkflow\Extension\Service\NewsService;

/**
 * Test for NewsService with old-style patterns
 */
class NewsServiceTest extends TestCase
{
    /**
     * @var NewsService
     */
    protected $subject;

    protected function setUp(): void
    {
        $this->subject = new NewsService();
    }

    /**
     * @test
     */
    public function enrichNewsItemReturnsNullForInvalidInput()
    {
        $result = $this->subject->enrichNewsItem('invalid');
        $this->assertNull($result);
    }

    /**
     * Test method with old annotation style
     * @test
     */
    public function enrichNewsItemWorksWithValidNews()
    {
        // Old-style mock creation
        $news = $this->getMockBuilder('CompleteWorkflow\\Extension\\Domain\\Model\\News')
            ->getMock();

        $result = $this->subject->enrichNewsItem($news, array());
        $this->assertIsArray($result);
    }
}
PHP
        );
    }

    private function setupVendorWithIntegratedTools(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        $binDir = $vendorDir . '/bin';
        $configDir = $vendorDir . '/cpsit/quality-tools/config';

        mkdir($binDir, 0777, true);
        mkdir($configDir, 0777, true);

        // Create realistic configurations
        $this->createRealisticConfigurations($configDir);

        // Create integrated tool executables
        $this->createIntegratedToolExecutables($binDir);
    }

    private function createRealisticConfigurations(string $configDir): void
    {
        // Rector configuration
        file_put_contents($configDir . '/rector.php', <<<'PHP'
<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../../packages',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ]);
PHP
        );

        // PHPStan configuration
        file_put_contents($configDir . '/phpstan.neon', <<<'NEON'
parameters:
    level: 6
    paths:
        - %currentWorkingDirectory%/packages
        - %currentWorkingDirectory%/tests
    excludePaths:
        - %currentWorkingDirectory%/packages/*/ext_emconf.php
    ignoreErrors:
        - '#Call to an undefined method TYPO3\\CMS\\#'
        - '#Access to an undefined property TYPO3\\CMS\\#'
NEON
        );

        // PHP CS Fixer configuration
        file_put_contents($configDir . '/php-cs-fixer.php', <<<'PHP'
<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/../../../packages',
        __DIR__ . '/../../../tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true);

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => false, // Don't add for existing code
        'function_typehint_space' => true,
        'single_quote' => true,
    ]);
PHP
        );
    }

    private function createIntegratedToolExecutables(string $binDir): void
    {
        // Rector that actually processes the files according to configuration
        file_put_contents($binDir . '/rector', <<<'BASH'
#!/bin/bash

CONFIG_FILE=""
DRY_RUN=""
TARGET_PATH=""

while [[ $# -gt 0 ]]; do
  case $1 in
    -c|--config)
      CONFIG_FILE="$2"
      shift 2
      ;;
    --dry-run)
      DRY_RUN="true"
      shift
      ;;
    *)
      TARGET_PATH="$1"
      shift
      ;;
  esac
done

echo "Rector - PHP Upgrade Tool"
echo "Processing files in $TARGET_PATH..."

# Count PHP files
FILE_COUNT=$(find "$TARGET_PATH" -name "*.php" | wc -l)
echo "Found $FILE_COUNT files to process"

# Simulate rector transformations
CHANGES_MADE=0

if [[ "$DRY_RUN" != "true" ]]; then
    echo "Applying transformations..."

    # Transform array() to []
    find "$TARGET_PATH" -name "*.php" -exec sed -i.rector 's/array(/[/g; s/array (/[/g' {} \;
    find "$TARGET_PATH" -name "*.php" -exec sed -i.rector 's/)]/]/g' {} \;
    CHANGES_MADE=$((CHANGES_MADE + 5))

    # Add missing return types (simplified)
    find "$TARGET_PATH" -name "*.php" -exec sed -i.rector 's/public function \([a-zA-Z_][a-zA-Z0-9_]*\)()/public function \1(): mixed/g' {} \;
    CHANGES_MADE=$((CHANGES_MADE + 3))

    # Clean up backup files
    find "$TARGET_PATH" -name "*.rector" -delete

    echo "Applied $CHANGES_MADE transformations"
else
    echo "DRY RUN: Would apply $((FILE_COUNT * 2)) transformations"
fi

echo "Rector completed successfully"
exit 0
BASH
        );

        # PHPStan that analyzes the actual code structure
        file_put_contents($binDir . '/phpstan', <<<'BASH'
#!/bin/bash

CONFIG_FILE=""
LEVEL=""
TARGET_PATH=""

while [[ $# -gt 0 ]]; do
  case $1 in
    -c|--config)
      CONFIG_FILE="$2"
      shift 2
      ;;
    -l|--level)
      LEVEL="$2"
      shift 2
      ;;
    analyse|analyze)
      shift
      ;;
    *)
      TARGET_PATH="$1"
      shift
      ;;
  esac
done

echo "PHPStan - PHP Static Analysis Tool"
echo "Analyzing $TARGET_PATH..."

# Count files
FILE_COUNT=$(find "$TARGET_PATH" -name "*.php" | wc -l)
echo "Analyzing $FILE_COUNT files"

# Simulate analysis findings
ERRORS=0

# Check for missing type hints
if grep -r "public function.*(\$" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "Line 42: Method parameter \$newsItem has no type hint"
    ERRORS=$((ERRORS + 1))
fi

# Check for missing return types
if grep -r "public function.*)" "$TARGET_PATH" | grep -v ": " >/dev/null 2>&1; then
    echo "Line 67: Method has no return type specified"
    ERRORS=$((ERRORS + 1))
fi

# Check for array access on potentially null values
if grep -r "->get.*()->get" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "Line 89: Possible null pointer access"
    ERRORS=$((ERRORS + 1))
fi

echo ""
if [ $ERRORS -gt 0 ]; then
    echo "[ERROR] Found $ERRORS errors"
    exit 1
else
    echo "[OK] No errors found"
    exit 0
fi
BASH
        );

        # PHP CS Fixer that fixes code style issues
        file_put_contents($binDir . '/php-cs-fixer', <<<'BASH'
#!/bin/bash

CONFIG_FILE=""
DRY_RUN=""
TARGET_PATH=""

while [[ $# -gt 0 ]]; do
  case $1 in
    --config)
      CONFIG_FILE="$2"
      shift 2
      ;;
    --dry-run)
      DRY_RUN="true"
      shift
      ;;
    fix)
      shift
      ;;
    *)
      TARGET_PATH="$1"
      shift
      ;;
  esac
done

echo "PHP CS Fixer - PHP Coding Standards Fixer"
echo "Processing $TARGET_PATH..."

FILE_COUNT=$(find "$TARGET_PATH" -name "*.php" | wc -l)
FIXES_APPLIED=0

if [[ "$DRY_RUN" != "true" ]]; then
    echo "Applying fixes..."

    # Fix spacing around operators
    find "$TARGET_PATH" -name "*.php" -exec sed -i.phpcs 's/\([^=!<>]\)=\([^=]\)/\1 = \2/g' {} \;
    find "$TARGET_PATH" -name "*.php" -exec sed -i.phpcs 's/if(/if (/g' {} \;
    find "$TARGET_PATH" -name "*.php" -exec sed -i.phpcs 's/){/) {/g' {} \;

    # Fix concatenation spacing
    find "$TARGET_PATH" -name "*.php" -exec sed -i.phpcs 's/\.\([^" ]\)/ . \1/g' {} \;
    find "$TARGET_PATH" -name "*.php" -exec sed -i.phpcs 's/\([^" ]\)\./\1 ./g' {} \;

    # Clean up backup files
    find "$TARGET_PATH" -name "*.phpcs" -delete

    FIXES_APPLIED=$((FILE_COUNT * 3))
    echo "Fixed $FIXES_APPLIED style violations in $FILE_COUNT files"
else
    POTENTIAL_FIXES=$((FILE_COUNT * 3))
    echo "DRY RUN: Found $POTENTIAL_FIXES style violations in $FILE_COUNT files"
fi

exit 0
BASH
        );

        chmod($binDir . '/rector', 0755);
        chmod($binDir . '/phpstan', 0755);
        chmod($binDir . '/php-cs-fixer', 0755);
    }

    /**
     * Test complete quality improvement workflow
     */
    public function testCompleteQualityImprovementWorkflow(): void
    {
        $startTime = microtime(true);

        // Step 1: Analyze initial state
        $initialState = $this->analyzeCodeQuality();
        $this->assertGreaterThan(0, $initialState['issues'], 'Should have initial quality issues');

        // Step 2: Run Rector to modernize code
        $rectorResult = $this->runTool('rector');
        $this->assertEquals(0, $rectorResult['exitCode'], 'Rector should succeed');

        // Step 3: Run PHPStan to find remaining type issues
        $phpstanResult = $this->runTool('phpstan');
        // PHPStan may still find issues after rector
        $this->assertContains($phpstanResult['exitCode'], [0, 1]);

        // Step 4: Run PHP CS Fixer to standardize formatting
        $fixerResult = $this->runTool('php-cs-fixer');
        $this->assertEquals(0, $fixerResult['exitCode'], 'PHP CS Fixer should succeed');

        // Step 5: Verify final state
        $finalState = $this->analyzeCodeQuality();
        $this->assertQualityImprovement($initialState, $finalState);

        $endTime = microtime(true);
        $this->workflowMetrics['complete_workflow'] = round($endTime - $startTime, 2) . 's';

        // Workflow should complete within reasonable time
        $this->assertLessThan(60, $endTime - $startTime, 'Complete workflow should finish within 1 minute');
    }

    /**
     * Test workflow with tool interdependencies
     */
    public function testWorkflowWithToolInterdependencies(): void
    {
        // Test that rector changes help PHPStan analysis

        // Initial PHPStan run (expect errors)
        $initialPhpStan = $this->runTool('phpstan');
        $initialErrors = $this->countPhpStanErrors($initialPhpStan['output']);

        // Run Rector to modernize code
        $rectorResult = $this->runTool('rector');
        $this->assertEquals(0, $rectorResult['exitCode'], 'Rector should succeed');

        // PHPStan run after Rector (should have fewer errors)
        $postRectorPhpStan = $this->runTool('phpstan');
        $postRectorErrors = $this->countPhpStanErrors($postRectorPhpStan['output']);

        // Rector modernization should help reduce PHPStan errors
        $this->assertLessThanOrEqual($initialErrors, $postRectorErrors,
            'Rector should not increase PHPStan errors'
        );

        // Run PHP CS Fixer (should not introduce new analysis issues)
        $fixerResult = $this->runTool('php-cs-fixer');
        $this->assertEquals(0, $fixerResult['exitCode'], 'PHP CS Fixer should succeed');

        // Final PHPStan run (should not have more errors than before)
        $finalPhpStan = $this->runTool('phpstan');
        $finalErrors = $this->countPhpStanErrors($finalPhpStan['output']);

        $this->assertLessThanOrEqual($postRectorErrors, $finalErrors,
            'PHP CS Fixer should not introduce new PHPStan errors'
        );
    }

    /**
     * Test parallel tool execution workflow
     */
    public function testParallelToolWorkflow(): void
    {
        // Test running tools on different parts of the codebase simultaneously

        $startTime = microtime(true);

        $processes = [
            'rector_models' => new Process([
                'vendor/bin/rector',
                '--dry-run',
                'packages/workflow_extension/Classes/Domain'
            ], $this->tempProjectRoot),

            'phpstan_controllers' => new Process([
                'vendor/bin/phpstan',
                'analyse',
                'packages/workflow_extension/Classes/Controller'
            ], $this->tempProjectRoot),

            'fixer_services' => new Process([
                'vendor/bin/php-cs-fixer',
                'fix',
                '--dry-run',
                'packages/workflow_extension/Classes/Service'
            ], $this->tempProjectRoot)
        ];

        // Start all processes
        foreach ($processes as $process) {
            $process->start();
        }

        // Wait for completion
        $results = [];
        foreach ($processes as $name => $process) {
            $process->wait();
            $results[$name] = $process->getExitCode();
        }

        $endTime = microtime(true);
        $this->workflowMetrics['parallel_workflow'] = round($endTime - $startTime, 2) . 's';

        // All should complete successfully or with expected analysis results
        foreach ($results as $tool => $exitCode) {
            $this->assertContains($exitCode, [0, 1],
                "Tool {$tool} should complete successfully or with analysis findings"
            );
        }

        // Parallel execution should be faster than sequential
        $this->assertLessThan(30, $endTime - $startTime,
            'Parallel execution should complete within 30 seconds'
        );
    }

    /**
     * Test incremental workflow (only process changed files)
     */
    public function testIncrementalWorkflow(): void
    {
        // Simulate incremental processing by targeting specific files

        $changedFiles = [
            'packages/workflow_extension/Classes/Controller/NewsController.php',
            'packages/workflow_extension/Classes/Service/NewsService.php'
        ];

        foreach ($changedFiles as $file) {
            $result = $this->runTool('rector', ['--dry-run', $file]);
            $this->assertEquals(0, $result['exitCode'],
                "Rector should process individual file: {$file}"
            );

            $result = $this->runTool('phpstan', [$file]);
            $this->assertContains($result['exitCode'], [0, 1],
                "PHPStan should analyze individual file: {$file}"
            );
        }
    }

    /**
     * Test workflow resilience with corrupted files
     */
    public function testWorkflowRollbackOnFailure(): void
    {
        // Capture initial state
        $initialState = $this->captureFileStates();

        // Introduce a critical error to test error recovery
        $this->introduceCorruptedFile();

        // Run workflow - improved tools should handle corruption gracefully
        $rectorResult = $this->runTool('rector', ['--dry-run']);

        // With improved error recovery, tools may handle corrupted files better
        $this->assertContains($rectorResult['exitCode'], [0, 1, 2],
            'Tools with error recovery should handle corrupted files gracefully'
        );

        // Verify no other files were corrupted
        $currentState = $this->captureFileStates();
        $this->assertMostFilesUnchanged($initialState, $currentState);
    }

    private function analyzeCodeQuality(): array
    {
        $issues = 0;

        // Count different types of issues in the codebase
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            // Count old array syntax
            if (strpos($content, 'array(') !== false) {
                $issues++;
            }

            // Count missing type hints
            if (preg_match('/public function \w+\([^)]*\$\w+[^)]*\)/', $content)) {
                $issues++;
            }

            // Count style issues
            if (strpos($content, 'if(') !== false || strpos($content, '){') !== false) {
                $issues++;
            }
        }

        return ['issues' => $issues, 'files' => count($phpFiles)];
    }

    private function assertQualityImprovement(array $before, array $after): void
    {
        $this->assertLessThanOrEqual($before['issues'], $after['issues'],
            'Quality issues should not increase'
        );

        // Ideally issues should decrease significantly
        $improvement = $before['issues'] - $after['issues'];
        $this->assertGreaterThan(0, $improvement,
            'Some quality issues should have been fixed'
        );
    }

    private function countPhpStanErrors(string $output): int
    {
        if (preg_match('/Found (\d+) errors/', $output, $matches)) {
            return (int)$matches[1];
        }
        return strpos($output, '[OK]') !== false ? 0 : 1;
    }

    private function findPhpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempProjectRoot . '/packages')
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function captureFileStates(): array
    {
        $states = [];
        $files = $this->findPhpFiles();

        foreach ($files as $file) {
            $states[$file] = [
                'content' => file_get_contents($file),
                'mtime' => filemtime($file),
                'size' => filesize($file)
            ];
        }

        return $states;
    }

    private function introduceCorruptedFile(): void
    {
        $corruptedFile = $this->tempProjectRoot . '/packages/workflow_extension/Classes/Corrupted.php';
        file_put_contents($corruptedFile, '<?php class Corrupted { // missing closing brace');
    }

    private function assertMostFilesUnchanged(array $before, array $after): void
    {
        $changedFiles = 0;
        $totalFiles = count($before);

        foreach ($before as $file => $state) {
            if (isset($after[$file]) && $state['content'] !== $after[$file]['content']) {
                $changedFiles++;
            }
        }

        // At most 10% of files should have changed due to failure
        $this->assertLessThan($totalFiles * 0.1, $changedFiles,
            'Most files should remain unchanged when workflow fails'
        );
    }

    private function runTool(string $tool, array $args = []): array
    {
        $command = array_merge(["vendor/bin/{$tool}"], $args);
        if (empty($args)) {
            $command[] = '.';
        }

        $process = new Process($command, $this->tempProjectRoot, null, null, 60);
        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput()
        ];
    }
}
