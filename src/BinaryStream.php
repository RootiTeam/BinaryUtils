<?php

declare(strict_types=1);

namespace pocketmine\utils;

#include <rules/BinaryIO.h>

use pocketmine\item\Item;
use InvalidArgumentException;
use function chr;
use function ord;
use function strlen;
use function substr;

class BinaryStream{

	public function __construct(
		public string $buffer = "",
		public int $offset = 0
	){
		//NOOP
	}

	/**
	 * Rewinds the stream pointer to the start.
	 */
	public function rewind() : void{
		$this->offset = 0;
	}

	public function reset() : void{
		$this->buffer = "";
		$this->offset = 0;
	}

	public function setOffset(int $offset) : void{
		$this->offset = $offset;
	}

	public function getOffset() : int{
		return $this->offset;
	}

	public function setBuffer(string $buffer, int $offset = 0) : void{
		$this->buffer = $buffer;
		$this->offset = $offset;
	}

	public function getBuffer() : string{
		return $this->buffer;
	}

	/**
	 * @param int $len
	 *
	 * @return string
	 */
	public function get(int $len) : string{
		if($len === 0){
			return "";
		}
		if($len < 0){
			throw new InvalidArgumentException("Length must be positive");
		}

		$remaining = strlen($this->buffer) - $this->offset;
		if($remaining < $len){
			throw new BinaryDataException("Not enough bytes left in buffer: need $len, have $remaining");
		}

		return $len === 1 ? $this->buffer[$this->offset++] : substr($this->buffer, ($this->offset += $len) - $len, $len);
	}

	/**
	 * @throws BinaryDataException
	 */
	public function getRemaining() : string{
		$buflen = strlen($this->buffer);
		if($this->offset >= $buflen){
			throw new BinaryDataException("No bytes left to read");
		}
		$str = substr($this->buffer, $this->offset);
		$this->offset = $buflen;
		return $str;
	}

	protected function getString() : string{
		return $this->get($this->getUnsignedVarInt());
	}

	protected function putString(string $v){
		$this->putUnsignedVarInt(strlen($v));
		$this->put($v);
	}

	public function put(string $str) : void{
		$this->buffer .= $str;
	}

	public function getBool() : bool{
		return $this->get(1) !== "\x00";
	}

	public function putBool(bool $v) : void{
		$this->buffer .= ($v ? "\x01" : "\x00");
	}

	public function getByte() : int{
		return ord($this->get(1));
	}

	public function getUUID() : UUID{
		//This is actually two little-endian longs: UUID Most followed by UUID Least
		$part1 = $this->getLInt();
		$part0 = $this->getLInt();
		$part3 = $this->getLInt();
		$part2 = $this->getLInt();
		return new UUID($part0, $part1, $part2, $part3);
	}

	public function putUUID(UUID $uuid) : void{
		$this->putLInt($uuid->getPart(1));
		$this->putLInt($uuid->getPart(0));
		$this->putLInt($uuid->getPart(3));
		$this->putLInt($uuid->getPart(2));
	}

	public function getSlot() : Item{
		$id = $this->getVarInt();
		if($id <= 0){
			return Item::get(0, 0, 0);
		}

		$auxValue = $this->getVarInt();
		$data = $auxValue >> 8;
		if($data === 0x7fff){
			$data = -1;
		}
		$cnt = $auxValue & 0xff;

		$nbtLen = $this->getLShort();
		$nbt = "";

		if($nbtLen > 0){
			$nbt = $this->get($nbtLen);
		}

		//TODO
		$canPlaceOn = $this->getVarInt();
		if($canPlaceOn > 0){
			for($i = 0; $i < $canPlaceOn; ++$i){
				$this->getString();
			}
		}

		//TODO
		$canDestroy = $this->getVarInt();
		if($canDestroy > 0){
			for($i = 0; $i < $canDestroy; ++$i){
				$this->getString();
			}
		}

		return Item::get($id, $data, $cnt, $nbt);
	}

	public function putSlot(Item $item) : void{
		if($item->getId() === 0){
			$this->putVarInt(0);
			return;
		}

		$this->putVarInt($item->getId());
		$auxValue = (($item->getDamage() & 0x7fff) << 8) | $item->getCount();
		$this->putVarInt($auxValue);

		$nbt = $item->getCompoundTag();
		$this->putLShort(strlen($nbt));
		$this->put($nbt);

		$this->putVarInt(0); //CanPlaceOn entry count (TODO)
		$this->putVarInt(0); //CanDestroy entry count (TODO)
	}

	public function putByte(int $v) : void{
		$this->buffer .= chr($v);
	}

	public function getShort() : int{
		return Binary::readShort($this->get(2));
	}

	public function getSignedShort() : int{
		return Binary::readSignedShort($this->get(2));
	}

	public function putShort(int $v) : void{
		$this->buffer .= Binary::writeShort($v);
	}

	public function getLShort() : int{
		return Binary::readLShort($this->get(2));
	}

	public function getSignedLShort() : int{
		return Binary::readSignedLShort($this->get(2));
	}

	public function putLShort(int $v) : void{
		$this->buffer .= Binary::writeLShort($v);
	}

	public function getTriad() : int{
		return Binary::readTriad($this->get(3));
	}

	public function putTriad(int $v) : void{
		$this->buffer .= Binary::writeTriad($v);
	}

	public function getLTriad() : int{
		return Binary::readLTriad($this->get(3));
	}

	public function putLTriad(int $v) : void{
		$this->buffer .= Binary::writeLTriad($v);
	}

	public function getInt() : int{
		return Binary::readInt($this->get(4));
	}

	public function putInt(int $v) : void{
		$this->buffer .= Binary::writeInt($v);
	}

	public function getLInt() : int{
		return Binary::readLInt($this->get(4));
	}

	public function putLInt(int $v) : void{
		$this->buffer .= Binary::writeLInt($v);
	}

	public function getFloat() : float{
		return Binary::readFloat($this->get(4));
	}

	public function getRoundedFloat(int $accuracy) : float{
		return Binary::readRoundedFloat($this->get(4), $accuracy);
	}

	public function putFloat(float $v) : void{
		$this->buffer .= Binary::writeFloat($v);
	}

	public function getLFloat() : float{
		return Binary::readLFloat($this->get(4));
	}

	public function getRoundedLFloat(int $accuracy) : float{
		return Binary::readRoundedLFloat($this->get(4), $accuracy);
	}

	public function putLFloat(float $v) : void{
		$this->buffer .= Binary::writeLFloat($v);
	}

	public function getDouble() : float{
		return Binary::readDouble($this->get(8));
	}

	public function putDouble(float $v) : void{
		$this->buffer .= Binary::writeDouble($v);
	}

	public function getLDouble() : float{
		return Binary::readLDouble($this->get(8));
	}

	public function putLDouble(float $v) : void{
		$this->buffer .= Binary::writeLDouble($v);
	}

	public function getLong() : int{
		return Binary::readLong($this->get(8));
	}

	public function putLong(int $v) : void{
		$this->buffer .= Binary::writeLong($v);
	}

	public function getLLong() : int{
		return Binary::readLLong($this->get(8));
	}

	public function putLLong(int $v) : void{
		$this->buffer .= Binary::writeLLong($v);
	}

	/**
	 * Reads a 32-bit variable-length unsigned integer from the buffer and returns it.
	 */
	public function getUnsignedVarInt() : int{
		return Binary::readUnsignedVarInt($this->buffer, $this->offset);
	}

	/**
	 * Writes a 32-bit variable-length unsigned integer to the end of the buffer.
	 *
	 * @param int $v
	 */
	public function putUnsignedVarInt(int $v) : void{
		$this->put(Binary::writeUnsignedVarInt($v));
	}

	/**
	 * Reads a 32-bit zigzag-encoded variable-length integer from the buffer and returns it.
	 */
	public function getVarInt() : int{
		return Binary::readVarInt($this->buffer, $this->offset);
	}

	/**
	 * Writes a 32-bit zigzag-encoded variable-length integer to the end of the buffer.
	 *
	 * @param int $v
	 */
	public function putVarInt(int $v) : void{
		$this->put(Binary::writeVarInt($v));
	}

	/**
	 * Reads a 64-bit variable-length integer from the buffer and returns it.
	 */
	public function getUnsignedVarLong() : int{
		return Binary::readUnsignedVarLong($this->buffer, $this->offset);
	}

	/**
	 * Writes a 64-bit variable-length integer to the end of the buffer.
	 *
	 * @param int $v
	 */
	public function putUnsignedVarLong(int $v) : void{
		$this->buffer .= Binary::writeUnsignedVarLong($v);
	}

	/**
	 * Reads a 64-bit zigzag-encoded variable-length integer from the buffer and returns it.
	 */
	public function getVarLong() : int{
		return Binary::readVarLong($this->buffer, $this->offset);
	}

	/**
	 * Writes a 64-bit zigzag-encoded variable-length integer to the end of the buffer.
	 *
	 * @param int $v
	 */
	public function putVarLong(int $v) : void{
		$this->buffer .= Binary::writeVarLong($v);
	}

	/**
	 * Returns whether the offset has reached the end of the buffer.
	 */
	public function feof() : bool{
		return !isset($this->buffer[$this->offset]);
	}
}
