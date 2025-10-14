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
        
        public function insertOne(array $data): ?string {
            try {
                $data['_id'] = new ObjectId();
                $data['createdAt'] = new UTCDateTime();
                $bulk = new \MongoDB\Driver\BulkWrite();
                $bulk->insert($data);
                $result = $this->manager->executeBulkWrite($this->namespace, $bulk);
                return (string)$data['_id'];
            } catch (MongoDBException $e) {
                error_log("Insert Error: " . $e->getMessage());
                return null;
            }
        }

        public function insertMany(array $documents): array {
            $insertedIds = [];
            try {
                $bulk = new \MongoDB\Driver\BulkWrite();
                foreach ($documents as $data) {
                    $data['_id'] = new ObjectId();
                    $data['createdAt'] = new UTCDateTime();
                    $insertedIds[] = (string)$data['_id'];
                    $bulk->insert($data);
                }
                $this->manager->executeBulkWrite($this->namespace, $bulk);
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