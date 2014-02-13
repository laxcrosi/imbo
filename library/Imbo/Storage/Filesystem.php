<?php
/**
 * This file is part of the Imbo package
 *
 * (c) Christer Edvartsen <cogo@starzinger.net>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace Imbo\Storage;

use Imbo\Exception\StorageException,
    Imbo\Exception,
    DateTime,
    DateTimeZone;

/**
 * Filesystem storage driver
 *
 * This storage driver stores image files in a local filesystem.
 *
 * Configuration options supported by this driver:
 *
 * - <pre>(string) dataDir</pre> Absolute path to the base directory the images should be stored in
 *
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @package Storage
 */
class Filesystem implements StorageInterface {
    /**
     * Parameters for the filesystem driver
     *
     * @var array
     */
    private $params = array(
        'dataDir' => null,
    );

    /**
     * Class constructor
     *
     * @param array $params Parameters for the driver
     */
    public function __construct(array $params) {
        $this->params = array_merge($this->params, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function store($publicKey, $imageIdentifier, $imageData) {
        if (!is_writable($this->params['dataDir'])) {
            throw new StorageException('Could not store image', 500);
        }

        if ($this->imageExists($publicKey, $imageIdentifier)) {
            return touch($this->getImagePath($publicKey, $imageIdentifier));
        }

        $imageDir = $this->getImagePath($publicKey, $imageIdentifier, false);
        $oldUmask = umask(0);

        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0775, true);
        }

        umask($oldUmask);

        $imagePath = $imageDir . '/' . $imageIdentifier;

        return (bool) file_put_contents($imagePath, $imageData);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($publicKey, $imageIdentifier) {
        if (!$this->imageExists($publicKey, $imageIdentifier)) {
            throw new StorageException('File not found', 404);
        }

        $path = $this->getImagePath($publicKey, $imageIdentifier);

        return unlink($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getImage($publicKey, $imageIdentifier) {
        if (!$this->imageExists($publicKey, $imageIdentifier)) {
            throw new StorageException('File not found', 404);
        }

        $path = $this->getImagePath($publicKey, $imageIdentifier);

        return file_get_contents($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastModified($publicKey, $imageIdentifier) {
        if (!$this->imageExists($publicKey, $imageIdentifier)) {
            throw new StorageException('File not found', 404);
        }

        $path = $this->getImagePath($publicKey, $imageIdentifier);

        // Get the unix timestamp
        $timestamp = filemtime($path);

        // Create a new datetime instance
        return new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus() {
        return is_writable($this->params['dataDir']);
    }

    /**
     * {@inheritdoc}
     */
    public function imageExists($publicKey, $imageIdentifier) {
        $path = $this->getImagePath($publicKey, $imageIdentifier);

        return file_exists($path);
    }

    /**
     * Get the path to an image
     *
     * @param string $publicKey The key
     * @param string $imageIdentifier Image identifier
     * @param boolean $includeFilename Whether or not to include the last part of the path (the
     *                                 filename itself)
     * @return string
     */
    private function getImagePath($publicKey, $imageIdentifier, $includeFilename = true) {
        $parts = array(
            $this->params['dataDir'],
            $publicKey[0],
            $publicKey[1],
            $publicKey[2],
            $publicKey,
            $imageIdentifier[0],
            $imageIdentifier[1],
            $imageIdentifier[2],
        );

        if ($includeFilename) {
            $parts[] = $imageIdentifier;
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
