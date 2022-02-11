<?php

declare(strict_types=1);

/**
 * @copyright 2022 Anna Larch <anna@nextcloud.com>
 *
 * @author 2022 Anna Larch <anna@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getType()
 * @method void setType(int $type)
 * @method int getAccountId()
 * @method void setAccountId(int $accountId)
 * @method int|null getAliasId()
 * @method void setAliasId(?int $aliasId)
 * @method int getSendAt()
 * @method void setSendAt(int $sendAt)
 * @method string getSubject()
 * @method void setSubject(string $subject)
 * @method string getBody()
 * @method void setBody(string $body)
 * @method bool isHtml()
 * @method void setHtml(bool $html)
 * @method int|null getInReplyToId()
 * @method void setInReplyToId(?int $inReplyToId)
 * @method int|null getDraftId()
 * @method void setDraftId(?int $draftId);
 */
class LocalMailboxMessage extends Entity implements JsonSerializable {

	/** @var int */
	protected $type;

	/** @var int */
	protected $accountId;

	/** @var int|null */
	protected $aliasId;

	/** @var int */
	protected $sendAt;

	/** @var string */
	protected $subject;

	/** @var string */
	protected $body;

	/** @var bool */
	protected $html;

	/** @var int|null */
	protected $inReplyToMessageId;

	/** @var int|null */
	protected $draftId;

	public const OUTGOING = 0;
	public const DRAFT = 1;

	public function __construct() {
		$this->addType('type', 'integer');
		$this->addType('accountId', 'integer');
		$this->addType('aliasId', 'integer');
		$this->addType('sendAt', 'integer');
		$this->addType('subject', 'string');
		$this->addType('body', 'string');
		$this->addType('html', 'boolean');
		$this->addType('inReplyToId', 'integer');
		$this->addType('draftId', 'integer');
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'type' => $this->getType(),
			'accountId' => $this->getAccountId(),
			'aliasId' => $this->getAccountId(),
			'sendAt' => $this->getSendAt(),
			'subject' => $this->getSubject(),
			'text' => $this->getBody(),
			'html' => ($this->isHtml() === true),
			'inReplyToId' => $this->getInReplyToId(),
			'draftId' => $this->getDraftId()
		];
	}
}
