<?php

namespace Civi\Coworker\Util;

use PHPUnit\Framework\TestCase;

class TaskSplitterTest extends TestCase {

  public function testNullIdentity() {
    $input = [
      $i1 = ['id' => '100', 'queue' => 'a', 'run_as' => NULL],
      $i2 = ['id' => '120', 'queue' => 'a', 'run_as' => NULL],
    ];
    $expected = [
      ['context' => 'd0-c0', 'items' => [$i1, $i2]],
    ];
    $actual = TaskSplitter::split($input);
    $this->assertEquals($expected, $actual);
  }

  public function testSingleIdentity() {
    $input = [
      $i1 = ['id' => '200', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
      $i2 = ['id' => '204', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
      $i3 = ['id' => '214', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
    ];
    $expected = [
      ['context' => 'd1-c10', 'items' => [$i1, $i2, $i3]],
    ];
    $actual = TaskSplitter::split($input);
    $this->assertEquals($expected, $actual);
  }

  public function testAlternateIdentities() {
    $input = [
      $i1 = ['id' => '201', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
      $i2 = ['id' => '202', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
      $i3 = ['id' => '203', 'queue' => 'b', 'run_as' => ['contactId' => 20, 'domainId' => 1]],
      $i4 = ['id' => '204', 'queue' => 'b', 'run_as' => ['contactId' => 20, 'domainId' => 1]],
      $i5 = ['id' => '205', 'queue' => 'b', 'run_as' => ['contactId' => 20, 'domainId' => 1]],
      $i6 = ['id' => '206', 'queue' => 'b', 'run_as' => ['contactId' => 10, 'domainId' => 1]],
      $i7 = ['id' => '207', 'queue' => 'b', 'run_as' => ['contactId' => 30, 'domainId' => 2]],
      $i8 = ['id' => '208', 'queue' => 'b', 'run_as' => ['contactId' => 30, 'domainId' => 2]],
    ];
    $expected = [
      ['context' => 'd1-c10', 'items' => [$i1, $i2]],
      ['context' => 'd1-c20', 'items' => [$i3, $i4, $i5]],
      ['context' => 'd1-c10', 'items' => [$i6]],
      ['context' => 'd2-c30', 'items' => [$i7, $i8]],
    ];
    $actual = TaskSplitter::split($input);
    $this->assertEquals($expected, $actual);
  }

  public function testEncodeDecodeContextName() {
    $this->assertEquals('d100-c200', TaskSplitter::encodeContextName(['contactId' => 200, 'domainId' => 100]));
    $this->assertEquals(['contactId' => 201, 'domainId' => 101], TaskSplitter::decodeContextName('d101-c201'));
    $this->assertEquals('d0-c0', TaskSplitter::encodeContextName(['contactId' => NULL, 'domainId' => NULL]));
    $this->assertEquals(['contactId' => NULL, 'domainId' => NULL], TaskSplitter::decodeContextName('d0-c0'));
  }

}
