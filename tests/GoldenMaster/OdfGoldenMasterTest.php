<?php

namespace Odtphp\Test\GoldenMaster;

use Odtphp\Odf;
use Odtphp\Test\Support\OdtSnapshotTestCase;

/**
 * Golden-master (characterization) tests for Odtphp\Odf.
 *
 * Each scenario reproduces one of the historical tests/tutorielN.php examples
 * and snapshots the resulting content.xml / manifest.xml. These tests protect
 * the CURRENT behaviour while the library is modernized (Phase 0 of the
 * migration plan) - see tests/GoldenMaster/__snapshots__ for the reference
 * output and tests/Support/OdtSnapshotTestCase for the snapshot mechanism.
 *
 * @covers \Odtphp\Odf
 * @covers \Odtphp\Segment
 * @covers \Odtphp\Zip\PclZipProxy
 * @covers \Odtphp\Zip\PhpZipProxy
 */
class OdfGoldenMasterTest extends OdtSnapshotTestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/odt';
    private const IMAGES = __DIR__ . '/../Fixtures/images';

    /** @var string[] temp files to clean up */
    private array $outputFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->outputFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->outputFiles = [];
    }

    private function saveAndExtract(Odf $odf, string $member): string
    {
        $out = tempnam(sys_get_temp_dir(), 'odtphp-golden-') . '.odt';
        $this->outputFiles[] = $out;
        $odf->saveToDisk($out);

        return $this->extractFromOdt($out, $member);
    }

    /**
     * Scenario from tests/tutoriel1.php: simple variable substitution,
     * default configuration (PclZipProxy, default delimiters).
     */
    public function testSimpleTextSubstitution(): void
    {
        $odf = new Odf(self::FIXTURES . '/tutoriel1.odt');
        $odf->setVars('titre', 'PHP: Hypertext Preprocessor');
        $odf->setVars('message', "Ligne 1 avec accents : \xe9\xe8\xe0\nLigne 2 apres un saut de ligne.");

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel1-content');
    }

    /**
     * Scenario from tests/tutoriel2.php: text substitution + a single image.
     */
    public function testTextSubstitutionWithSingleImage(): void
    {
        $odf = new Odf(self::FIXTURES . '/tutoriel2.odt');
        $odf->setVars('titre', 'Anaska formation');
        $odf->setVars('message', 'Anaska, leader Francais de la formation informatique.');
        $odf->setImage('image', self::IMAGES . '/anaska.jpg');

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $manifestXml = $this->saveAndExtract($odf, 'META-INF/manifest.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel2-content');
        $this->assertMatchesXmlSnapshot($manifestXml, 'tutoriel2-manifest');
    }

    /**
     * Scenario from tests/tutoriel3.php: merging a segment (loop) with data.
     */
    public function testSegmentLoop(): void
    {
        $odf = new Odf(self::FIXTURES . '/tutoriel3.odt');
        $odf->setVars('titre', "Quelques articles de l'encyclopedie");
        $odf->setVars('message', 'Message d\'introduction.');

        $listeArticles = [
            ['titre' => 'PHP', 'texte' => 'Langage de script.'],
            ['titre' => 'MySQL', 'texte' => 'Systeme de gestion de base de donnees.'],
            ['titre' => 'Apache', 'texte' => 'Logiciel de serveur HTTP.'],
        ];

        $article = $odf->setSegment('articles');
        foreach ($listeArticles as $element) {
            $article->titreArticle($element['titre']);
            $article->texteArticle($element['texte']);
            $article->merge();
        }
        $odf->mergeSegment($article);

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel3-content');
    }

    /**
     * Scenario from tests/tutoriel4.php: nested (imbricated) segments.
     */
    public function testNestedSegments(): void
    {
        $odf = new Odf(self::FIXTURES . '/tutoriel4.odt');
        $odf->setVars('titre', 'Articles disponibles :');

        $categorie = $odf->setSegment('categories');
        for ($j = 1; $j <= 2; $j++) {
            $categorie->setVars('TitreCategorie', 'Categorie ' . $j);
            for ($i = 1; $i <= 3; $i++) {
                $categorie->articles->titreArticle('Article ' . $i);
                $categorie->articles->date('01/01/2024');
                $categorie->articles->merge();
            }
            for ($i = 1; $i <= 4; $i++) {
                $categorie->commentaires->texteCommentaire('Commentaire ' . $i);
                $categorie->commentaires->merge();
            }
            $categorie->merge();
        }
        $odf->mergeSegment($categorie);

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel4-content');
    }

    /**
     * Scenario from tests/tutoriel5.php: segment loop with per-item images.
     */
    public function testSegmentLoopWithImages(): void
    {
        $odf = new Odf(self::FIXTURES . '/tutoriel5.odt');
        $odf->setVars('titre', "Quelques articles de l'encyclopedie");
        $odf->setVars('message', 'Message d\'introduction.');

        $listeArticles = [
            ['titre' => 'PHP', 'texte' => 'Langage de script.', 'image' => self::IMAGES . '/php.gif'],
            ['titre' => 'MySQL', 'texte' => 'SGBD.', 'image' => self::IMAGES . '/mysql.gif'],
            ['titre' => 'Apache', 'texte' => 'Serveur HTTP.', 'image' => self::IMAGES . '/apache.gif'],
        ];

        $article = $odf->setSegment('articles');
        foreach ($listeArticles as $element) {
            $article->titreArticle($element['titre']);
            $article->texteArticle($element['texte']);
            $article->setImage('image', $element['image']);
            $article->merge();
        }
        $odf->mergeSegment($article);

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $manifestXml = $this->saveAndExtract($odf, 'META-INF/manifest.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel5-content');
        $this->assertMatchesXmlSnapshot($manifestXml, 'tutoriel5-manifest');
    }

    /**
     * Scenario from tests/tutoriel7.php: explicit configuration array
     * (PhpZipProxy + custom '#' delimiters instead of the defaults).
     */
    public function testCustomConfigurationWithPhpZipProxyAndCustomDelimiters(): void
    {
        $config = [
            'ZIP_PROXY' => \Odtphp\Zip\PhpZipProxy::class,
            'DELIMITER_LEFT' => '#',
            'DELIMITER_RIGHT' => '#',
        ];
        $odf = new Odf(self::FIXTURES . '/tutoriel7.odt', $config);
        $odf->setVars('titre', 'PHP: Hypertext Preprocessor');
        $odf->setVars('message', 'Langage de script libre.');

        $contentXml = $this->saveAndExtract($odf, 'content.xml');
        $this->assertMatchesXmlSnapshot($contentXml, 'tutoriel7-content');
    }

    /**
     * Same content substitution as testSimpleTextSubstitution, but forcing
     * the PclZip-based proxy explicitly. Locks in behavioural parity between
     * the two ZipInterface implementations ahead of the Phase 4 decision.
     */
    public function testSameOutputWithBothZipProxies(): void
    {
        $odfPclZip = new Odf(self::FIXTURES . '/tutoriel1.odt', [
            'ZIP_PROXY' => \Odtphp\Zip\PclZipProxy::class,
        ]);
        $odfPclZip->setVars('titre', 'Titre');
        $odfPclZip->setVars('message', 'Message');
        $contentPclZip = $this->normalizeXml($this->saveAndExtract($odfPclZip, 'content.xml'));

        $odfPhpZip = new Odf(self::FIXTURES . '/tutoriel1.odt', [
            'ZIP_PROXY' => \Odtphp\Zip\PhpZipProxy::class,
        ]);
        $odfPhpZip->setVars('titre', 'Titre');
        $odfPhpZip->setVars('message', 'Message');
        $contentPhpZip = $this->normalizeXml($this->saveAndExtract($odfPhpZip, 'content.xml'));

        self::assertXmlStringEqualsXmlString(
            $contentPclZip,
            $contentPhpZip,
            'PclZipProxy and PhpZipProxy must produce identical content.xml for the same operations.'
        );
    }
}
