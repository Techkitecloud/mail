<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\Tests\Unit\Service\Autoconfig;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Service\AutoConfig\MxRecord;
use Psr\Log\LoggerInterface;

class MxRecordTest extends TestCase {

	/** @var MxRecord */
	private $record;

	protected function setUp(): void {
		parent::setUp();

		$logger = $this->createMock(LoggerInterface::class);

		$this->record = new MxRecord($logger);
	}

	public function testQuery(): void {
		$records = $this->record->query('nextcloud.com');

		$this->assertIsArray($records);
		$this->assertNotEmpty($records);
	}

	public function testQueryNoRecord(): void {
		$records = $this->record->query('example.com');

		$this->assertFalse($records);
	}

	public function testGetSanitizedRecords(): void {
		$records = ['mx.google.com',
			'testing.google.com',
			'try.again.de',
			'eins.zwei.drei.de'
		];

		$mxRecords = $this->record->getSanitizedRecords($records);
		$this->assertContains('google.com', $mxRecords);
		$this->assertContains('again.de', $mxRecords);
		$this->assertContains('mx.google.com', $mxRecords);
		$this->assertContains('testing.google.com', $mxRecords);
		$this->assertContains('try.again.de', $mxRecords);
		$this->assertContains('drei.de', $mxRecords);
		$this->assertContains('eins.zwei.drei.de', $mxRecords);

		$this->assertNotContains('', $mxRecords);
		$this->assertNotContains('gaga.uh.lala', $mxRecords);
	}
}
