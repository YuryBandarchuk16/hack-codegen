<?hh
/**
 * Copyright (c) 2015-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */

namespace Facebook\HackCodegen;

final class CodegenFileTestCase extends CodegenBaseTest {


  public function testAutogenerated() {
    $code = test_codegen_file('no_file')
      ->setDocBlock('Completely autogenerated!')
      ->addClass(
        codegen_class('AllAutogenerated')
          ->addMethod(
            codegen_method('getName')
            ->setReturnType('string')
            ->setBody('return $this->name;')
          )
      )
      ->render();

    self::assertUnchanged($code);
  }

  public static function testGenerateTopLevelFunctions() {
    $function = codegen_function('fun')
      ->setReturnType('int')
      ->setBody('return 0;');
    $code = test_codegen_file('no_file')
      ->addFunction($function)
      ->render();

    self::assertUnchanged($code);
  }

  public function testPartiallyGenerated() {
    $code = test_codegen_file('no_file')
      ->addClass(
        codegen_class('PartiallyGenerated')
          ->addMethod(
            codegen_method('getSomething')
              ->setManualBody()
          )
      )
      ->addClass(
        codegen_class('PartiallyGeneratedLoader')
        ->setDocBlock('We can put many clases in one file!')
      )
      ->render();

    self::assertUnchanged($code);
  }

  private function saveAutogeneratedFile(?string $fname = null) {
    if (!$fname) {
      $fname = Filesystem::createTemporaryFile('codegen', true);
    }

    test_codegen_file($fname)
      ->setDocBlock('Testing CodegenFile with autogenerated files')
      ->addClass(
        codegen_class('Demo')
          ->addMethod(codegen_method('getName')->setBody('return "Codegen";'))
      )
      ->save();

    return $fname;
  }

  private function saveManuallyWrittenFile(?string $fname = null) {
    if (!$fname) {
      $fname = Filesystem::createTemporaryFile('codegen', true);
    }
    Filesystem::writeFileIfChanged(
      $fname,
      "<?php\n".
      "// Some handwritten code"
    );
    return $fname;
  }

  private function savePartiallyGeneratedFile(
    ?string $fname = null,
    bool $extra_method = false) {

    if (!$fname) {
      $fname = Filesystem::createTemporaryFile('codegen', true);
    }

    $class = codegen_class('Demo')
      ->addMethod(
        codegen_method('getName')
          ->setBody('// manual_section_here')
          ->setManualBody()
      );

    if ($extra_method) {
      $class->addMethod(
        codegen_method('extraMethod')->setManualBody()
      );
    }

    test_codegen_file($fname)
      ->setDocBlock('Testing CodegenFile with partially generated files')
      ->addClass($class)
      ->save();

    return $fname;
  }

  public function testSaveAutogenerated() {
    $fname = $this->saveAutogeneratedFile();
    self::assertUnchanged(Filesystem::readFile($fname));
  }

  /**
   * @expectedException CodegenFileNoSignatureException
   */
  public function testClobberManuallyWrittenCode() {
    $fname = $this->saveManuallyWrittenFile();
    $this->saveAutogeneratedFile($fname);
  }

  public function testReSaveAutogenerated() {
    $fname = $this->saveAutogeneratedFile();
    $content0 = Filesystem::readFile($fname);
    $this->saveAutogeneratedFile($fname);
    $content1 = Filesystem::readFile($fname);
    self::assertEquals($content0, $content1);
  }

  /**
   * @expectedException CodegenFileBadSignatureException
   */
  public function testSaveModifiedAutogenerated() {
    $fname = $this->saveAutogeneratedFile();
    $content = Filesystem::readFile($fname);
    Filesystem::writeFile($fname, $content.'.');
    $this->saveAutogeneratedFile($fname);
  }


  public function testSavePartiallyGenerated() {
    $fname = $this->savePartiallyGeneratedFile();
    $content = Filesystem::readFile($fname);
    self::assertUnchanged($content);
    self::assertTrue(
      PartiallyGeneratedSignedSource::hasValidSignature($content)
    );
  }

  public function testReSavePartiallyGenerated() {
    $fname = $this->savePartiallyGeneratedFile();
    $content0 = Filesystem::readFile($fname);
    $this->savePartiallyGeneratedFile($fname);
    $content1 = Filesystem::readFile($fname);
    self::assertEquals($content0, $content1);
  }

  /**
   * @expectedException CodegenFileBadSignatureException
   */
  public function testSaveModifiedWrongPartiallyGenerated() {
    $fname = $this->savePartiallyGeneratedFile();
    $content = Filesystem::readFile($fname);
    Filesystem::writeFile($fname, $content.'.');
    $this->saveAutogeneratedFile($fname);
  }

  private function createAndModifyPartiallyGeneratedFile() {
    $fname = $this->savePartiallyGeneratedFile();
    $content = Filesystem::readFile($fname);

    $new_content = str_replace(
      '// manual_section_here',
      'return $this->name;',
      $content
    );
    self::assertFalse(
      $content == $new_content,
      "The manual content wasn't replaced. Please fix the test setup!"
    );
    Filesystem::writeFile($fname, $new_content);
    return $fname;
  }

  /**
   * Test modifying a manual section and saving.
   */
  public function testSaveModifiedManualSectionPartiallyGenerated() {
    $fname = $this->createAndModifyPartiallyGeneratedFile();
    $this->savePartiallyGeneratedFile($fname);
    $content = Filesystem::readFile($fname);
    self::assertTrue(strpos($content, 'this->name') !== false);
  }

  /**
   * Test modifying a manual section and changing the code generation so
   * that the generated part is different too.
   */
  public function testSaveModifyPartiallyGenerated() {
    $fname = $this->createAndModifyPartiallyGeneratedFile();
    $this->savePartiallyGeneratedFile($fname, true);
    $content = Filesystem::readFile($fname);
    self::assertTrue(strpos($content, 'return $this->name;') !== false);
    self::assertTrue(strpos($content, 'function extraMethod()') !== false);
  }

  public function testNoSignature() {
    $code = test_codegen_file('no_file')
      ->setIsSignedFile(false)
      ->setDocBlock('Completely autogenerated!')
      ->addClass(
        codegen_class('NoSignature')
          ->addMethod(
            codegen_method('getName')
            ->setReturnType('string')
            ->setBody('return $this->name;')
          )
      )
      ->render();

    self::assertUnchanged($code);
  }

  public function testNamespace() {
    $code = test_codegen_file('no_file')
      ->setNamespace('MyNamespace')
      ->useNamespace('Another\Space')
      ->useClass('My\Space\Bar', 'bar')
      ->useFunction('My\Space\my_function', 'f')
      ->useConst('My\Space\MAX_RETRIES')
      ->addClass(
        codegen_class('Foo')
      )
      ->render();

    self::assertUnchanged($code);
  }

  public function testStrictFile() {
    $code = test_codegen_file('no_file')
      ->setIsStrict(true)
      ->addClass(codegen_class('Foo'))
      ->render();

    self::assertUnchanged($code);
  }

  public function testPhpFile() {
    $code = test_codegen_file('no_file')
      ->setFileType(CodegenFileType::PHP)
      ->addClass(codegen_class('Foo'))
      ->render();

    self::assertUnchanged($code);
  }

}
