<?php
declare(strict_types=1);
namespace App\Crud;

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection as MongoCollection;
use stdClass;

class Mongo {
    private Client $client;
    private MongoCollection $collection;
    private string $db;
    private string $collectionName;

    public function __construct(string $uri, string $db, string $collection) {
        $this->client = new Client($uri);
        $this->db = $db;
        $this->collectionName = $collection;
        $this->collection = $this->client->selectCollection($this->db, $this->collectionName);
    }

    public function insertOne(array $data): mixed {
        try {
            $doc = array_merge($data, [
                '_id' => new ObjectId(),
                'createdAt' => new UTCDateTime()
            ]);

            $result = $this->collection->insertOne($doc);
            return $result ? (string)$result->getInsertedId() : null;
        } catch (\Throwable $e) {
            error_log("Insert Error: " . $e->getMessage());
            return null;
        }
    }

    public function insertMany(array $documents): mixed {
        try {
            $docs = array_map(function($doc) {
                $doc['_id'] = new ObjectId();
                $doc['createdAt'] = new UTCDateTime();
                return $doc;
            }, $documents);

            $result = $this->collection->insertMany($docs);
            $ids = array_map(fn($id) => (string)$id, $result->getInsertedIds());
            return $ids;
        } catch (\Throwable $e) {
            error_log("Insert Many Error: " . $e->getMessage());
            return [];
        }
    }

    public function find(array $filter = [], array $options = []): array {
        try {
            $cursor = $this->collection->find($filter, $options);
            return $cursor->toArray();
        } catch (\Throwable $e) {
            error_log("Find Error: " . $e->getMessage());
            return [];
        }
    }

    public function findOneByIdnumber(string $idnumber): ?stdClass {
        try {
            $result = $this->collection->findOne(['idnumber' => $idnumber]);
            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Find One Error: " . $e->getMessage());
            return null;
        }
    }

    public function findOneById(string $id): ?stdClass {
        try {
            $oid = new ObjectId($id);
            $result = $this->collection->findOne(['_id' => $oid]);
            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Find One Error: " . $e->getMessage());
            return null;
        }
    }

    public function updateOne(array $filter, array $update): array {
        try {
            $update['updatedAt'] = new UTCDateTime();
            $result = $this->collection->updateOne($filter, ['$set' => $update], ['upsert' => false]);
            return [
                'matchedCount' => $result->getMatchedCount(),
                'modifiedCount' => $result->getModifiedCount(),
                'upsertedId' => $result->getUpsertedId()
            ];
        } catch (\Throwable $e) {
            error_log("Update Error: " . $e->getMessage());
            return [];
        }
    }

    public function deleteOne(array $filter): array {
        try {
            $result = $this->collection->deleteOne($filter);
            return ['deletedCount' => $result->getDeletedCount()];
        } catch (\Throwable $e) {
            error_log("Delete One Error: " . $e->getMessage());
            return [];
        }
    }

    public function deleteMany(array $filter): array {
        try {
            $result = $this->collection->deleteMany($filter);
            return ['deletedCount' => $result->getDeletedCount()];
        } catch (\Throwable $e) {
            error_log("Delete Many Error: " . $e->getMessage());
            return [];
        }
    }

    public function count(array $filter = []): int {
        try {
            return (int)$this->collection->countDocuments($filter);
        } catch (\Throwable $e) {
            error_log("Count Error: " . $e->getMessage());
            return 0;
        }
    }
}