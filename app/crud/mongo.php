<?php
    declare(strict_types=1);
    namespace App\Crud;
    use MongoDB\Driver\Manager;
    use MongoDB\Driver\Command;
    use MongoDB\Driver\Query;
    use MongoDB\Driver\Exception\Exception as MongoDBException;
    use MongoDB\BSON\ObjectId;
    use MongoDB\BSON\UTCDateTime;
    use stdClass;

    class Mongo {
        private Manager $manager;
        private string $db;
        private string $collection;
        private string $namespace;
        
        public function __construct(string $uri, string $db, string $collection) {
            $this->manager = new Manager($uri);
            $this->db = $db;
            $this->collection = $collection;
            $this->namespace = "{$db}.{$collection}";
        }
        
        public function insertOne(array $data): mixed {
            try {
                $cmd = new Command([
                    'insert' => $this->collection,
                    'documents' => [
                        array_merge($data, [
                            '_id' => new ObjectId(),
                            'createdAt' => new UTCDateTime()
                        ])
                    ]
                ]);
                $result = $this->manager->executeCommand($this->db, $cmd)->toArray()[0] ?? null;
                return $result ? (string)$data['_id'] : null;
            } catch (MongoDBException $e) {
                error_log("Insert Error: " . $e->getMessage());
                return null;
            }
        }

        public function insertMany(array $documents): mixed {
            $insertedIds = [];
            try {
                $cmd = new Command([
                    'insert' => $this->collection,
                    'documents' => array_map(function($doc) use (&$insertedIds) {
                        $doc['_id'] = new ObjectId();
                        $doc['createdAt'] = new UTCDateTime();
                        $insertedIds[] = (string)$doc['_id'];
                        return $doc;
                    }, $documents)
                ]);
                return $this->manager->executeCommand($this->db, $cmd)->toArray()[0] ?? null;
            } catch (MongoDBException $e) {
                error_log("Insert Many Error: " . $e->getMessage());
            }
            return $insertedIds;
        }

        public function find(array $filter = [], array $options = []): array {
            try {
                $query = new Query($filter, $options);
                $cursor = $this->manager->executeQuery($this->namespace, $query);
                return $cursor->toArray();
            } catch (MongoDBException $e) {
                error_log("Find Error: " . $e->getMessage());
                return [];
            }
        }   

        public function findOneByIdnumber(string $idnumber): ?stdClass {
            try {
                $filter = ['idnumber' => $idnumber];
                $query = new Query($filter);
                $cursor = $this->manager->executeQuery($this->namespace, $query);
                $result = $cursor->toArray();
                return $result[0] ?? null;
            } catch (MongoDBException $e) {
                error_log("Find One Error: " . $e->getMessage());
                return null;
            }
        }

        public function findOneById(string $id): ?stdClass {
            try {
                $filter = ['_id' => new ObjectId($id)];
                $query = new Query($filter);
                $cursor = $this->manager->executeQuery($this->namespace, $query);
                $result = $cursor->toArray();
                return $result[0] ?? null;
            } catch (MongoDBException $e) {
                error_log("Find One Error: " . $e->getMessage());
                return null;
            }
        }

        public function updateOne(array $filter, array $update): array {
            $update['updatedAt'] = new UTCDateTime();
            $cmd = new Command([
                'update' => $this->collection,
                'updates' => [
                    [
                        'q' => $filter,
                        'u' => ['$set' => $update],
                        'upsert' => false,
                        'multi' => false
                    ]
                ]
            ]);
            return (array)$this->manager->executeCommand($this->db, $cmd)->toArray()[0];
        }

        public function deleteOne(array $filter): array {
            $cmd = new Command([
                'delete' => $this->collection,
                'deletes' => [
                    [
                        'q' => $filter,
                        'limit' => 1
                    ]
                ]
            ]);
            return (array)$this->manager->executeCommand($this->db, $cmd)->toArray()[0];
        }

        public function deleteMany(array $filter): array {
            $cmd = new Command([
                'delete' => $this->collection,
                'deletes' => [
                    [
                        'q' => $filter,
                        'limit' => 0
                    ]
                ]
            ]);
            return (array)$this->manager->executeCommand($this->db, $cmd)->toArray()[0];
        }

        public function count(array $filter = []): int {
            try {
                $cmd = new Command([
                    'count' => $this->collection,
                    'query' => $filter
                ]);
                $result = $this->manager->executeCommand($this->db, $cmd)->toArray()[0];
                return $result->n ?? 0;
            } catch (MongoDBException $e) {
                error_log("Count Error: " . $e->getMessage());
                return 0;
            }
        }   
    }