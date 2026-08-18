<?php

namespace App\Services\Questions;

use ZipArchive;

/**
 * Zabalí jeden PNG obrázok do `.pptx` s jedinou snímkou na celú plochu.
 *
 * Prečo ručne a nie knižnicou: `.pptx` je obyčajný ZIP s hŕstkou XML súborov
 * a pre jedinú snímku s jediným obrázkom je z nich premenná len jedna —
 * samotný obrázok. Pridať kvôli tomu ďalšiu composer závislosť (a s ňou celý
 * OOXML framework) by bolo neúmerné; projekt si drží veľmi štíhly `require`.
 *
 * Čo sa tu NESMIE „upratať" — každá z týchto vecí spôsobí v PowerPointe hlášku
 * „Našiel sa problém s obsahom" bez akéhokoľvek náznaku, kde je chyba:
 *
 * - `ppt/theme/theme1.xml` je povinný a schéma je prísna na počty detí:
 *   `clrScheme` musí mať presne dvanásť prvkov v danom poradí a každý zoznam
 *   v `fmtScheme` aspoň tri položky.
 * - `[Content_Types].xml` potrebuje `Default` pre `rels`, `xml` **a `png`**.
 *   Bez `png` sa snímka otvorí, ale obrázok bude prázdny rámik.
 * - Vzťah medzi `slideLayout` a `slideMaster` musí byť obojsmerný.
 * - `sldMasterId` a `sldLayoutId` musia byť ≥ 2147483648, `sldId` v rozsahu
 *   256–2147483647.
 * - `<p:sldSz>` je bez atribútu `type`: `type="screen16x9"` je predvoľba
 *   10×5,625″ a niektoré programy ju uprednostnia pred zadanými rozmermi.
 * - `r:embed` v snímke musí ukazovať na `rId` **obrázka**, nie layoutu.
 */
class PptxPackager
{
    /** 13,333″ × 7,5″ v EMU — širokouhlá snímka, ako ju robí PowerPoint 2013+. */
    public const SLIDE_WIDTH_EMU = 12192000;

    public const SLIDE_HEIGHT_EMU = 6858000;

    /**
     * @param string $png bajty obrázka 16:9
     * @return string bajty .pptx súboru
     */
    public function package(string $png, string $title = 'Q&A'): string
    {
        // ZipArchive nevie zapisovať do pamäťového streamu, takže cez dočasný
        // súbor. Ten žije len po dobu balenia a hneď mizne — snímka sa nikam
        // neukladá, presne ako QR kódy vstupeniek.
        $tmp = tempnam(sys_get_temp_dir(), 'qslide');

        if ($tmp === false) {
            abort(500, 'Nepodarilo sa vytvoriť dočasný súbor pre .pptx.');
        }

        try {
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE);

            foreach ($this->parts($title) as $path => $xml) {
                $zip->addFromString($path, $xml);
            }

            $zip->addFromString('ppt/media/image1.png', $png);
            // PNG je už komprimovaný, deflate by na ňom len horel čas.
            $zip->setCompressionName('ppt/media/image1.png', ZipArchive::CM_STORE);

            $zip->close();

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return array<string, string>
     */
    private function parts(string $title): array
    {
        $safeTitle = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return [
            '[Content_Types].xml' => self::CONTENT_TYPES,
            '_rels/.rels' => self::ROOT_RELS,
            'docProps/core.xml' => $this->coreProps($safeTitle),
            'ppt/presentation.xml' => self::PRESENTATION,
            'ppt/_rels/presentation.xml.rels' => self::PRESENTATION_RELS,
            'ppt/slideMasters/slideMaster1.xml' => self::SLIDE_MASTER,
            'ppt/slideMasters/_rels/slideMaster1.xml.rels' => self::SLIDE_MASTER_RELS,
            'ppt/slideLayouts/slideLayout1.xml' => self::SLIDE_LAYOUT,
            'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => self::SLIDE_LAYOUT_RELS,
            'ppt/slides/slide1.xml' => $this->slide($safeTitle),
            'ppt/slides/_rels/slide1.xml.rels' => self::SLIDE_RELS,
            'ppt/theme/theme1.xml' => self::THEME,
        ];
    }

    private function coreProps(string $safeTitle): string
    {
        $now = now()->toIso8601ZuluString();

        return <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
          <dc:title>{$safeTitle}</dc:title>
          <dcterms:created xsi:type="dcterms:W3CDTF">{$now}</dcterms:created>
          <dcterms:modified xsi:type="dcterms:W3CDTF">{$now}</dcterms:modified>
        </cp:coreProperties>
        XML;
    }

    private function slide(string $safeTitle): string
    {
        $w = self::SLIDE_WIDTH_EMU;
        $h = self::SLIDE_HEIGHT_EMU;

        return <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
          <p:cSld>
            <p:spTree>
              <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
              <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
              <p:pic>
                <p:nvPicPr>
                  <p:cNvPr id="2" name="{$safeTitle}"/>
                  <p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr>
                  <p:nvPr/>
                </p:nvPicPr>
                <p:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>
                <p:spPr>
                  <a:xfrm><a:off x="0" y="0"/><a:ext cx="{$w}" cy="{$h}"/></a:xfrm>
                  <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                </p:spPr>
              </p:pic>
            </p:spTree>
          </p:cSld>
          <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
        </p:sld>
        XML;
    }

    private const CONTENT_TYPES = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
      <Default Extension="png" ContentType="image/png"/>
      <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
      <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
      <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>
      <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>
      <Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
      <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
    </Types>
    XML;

    private const ROOT_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
      <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    </Relationships>
    XML;

    private const PRESENTATION = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
      <p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>
      <p:sldIdLst><p:sldId id="256" r:id="rId2"/></p:sldIdLst>
      <p:sldSz cx="12192000" cy="6858000"/>
      <p:notesSz cx="6858000" cy="9144000"/>
    </p:presentation>
    XML;

    private const PRESENTATION_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>
      <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
      <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
    </Relationships>
    XML;

    private const SLIDE_MASTER = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
      <p:cSld>
        <p:bg><p:bgPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>
        <p:spTree>
          <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
          <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
        </p:spTree>
      </p:cSld>
      <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>
      <p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>
    </p:sldMaster>
    XML;

    private const SLIDE_MASTER_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
      <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>
    </Relationships>
    XML;

    private const SLIDE_LAYOUT = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">
      <p:cSld name="Blank">
        <p:spTree>
          <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
          <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
        </p:spTree>
      </p:cSld>
      <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
    </p:sldLayout>
    XML;

    private const SLIDE_LAYOUT_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
    </Relationships>
    XML;

    /** rId1 = layout, rId2 = obrázok. `r:embed` v slide1.xml musí sedieť na rId2. */
    private const SLIDE_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
      <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>
    </Relationships>
    XML;

    private const THEME = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Otazky">
      <a:themeElements>
        <a:clrScheme name="Otazky">
          <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
          <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
          <a:dk2><a:srgbClr val="0F172A"/></a:dk2>
          <a:lt2><a:srgbClr val="E2E8F0"/></a:lt2>
          <a:accent1><a:srgbClr val="2563EB"/></a:accent1>
          <a:accent2><a:srgbClr val="0EA5E9"/></a:accent2>
          <a:accent3><a:srgbClr val="14B8A6"/></a:accent3>
          <a:accent4><a:srgbClr val="F59E0B"/></a:accent4>
          <a:accent5><a:srgbClr val="EF4444"/></a:accent5>
          <a:accent6><a:srgbClr val="8B5CF6"/></a:accent6>
          <a:hlink><a:srgbClr val="2563EB"/></a:hlink>
          <a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink>
        </a:clrScheme>
        <a:fontScheme name="Otazky">
          <a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>
          <a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>
        </a:fontScheme>
        <a:fmtScheme name="Otazky">
          <a:fillStyleLst>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
          </a:fillStyleLst>
          <a:lnStyleLst>
            <a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>
            <a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>
            <a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>
          </a:lnStyleLst>
          <a:effectStyleLst>
            <a:effectStyle><a:effectLst/></a:effectStyle>
            <a:effectStyle><a:effectLst/></a:effectStyle>
            <a:effectStyle><a:effectLst/></a:effectStyle>
          </a:effectStyleLst>
          <a:bgFillStyleLst>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
          </a:bgFillStyleLst>
        </a:fmtScheme>
      </a:themeElements>
    </a:theme>
    XML;
}
