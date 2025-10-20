<?php
namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
// Remove this if Payload model doesn't exist or has issues
// use app\models\Payload;

class PayloadController extends ActiveController
{
    public $modelClass = 'app\models\Payload';
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        unset($behaviors['authenticator']);

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        return $behaviors;
    }

    public function actionCreatee()
    {
        $request = Yii::$app->request;

        try {
            $currentDateTime = date('Y-m-d H:i:s');

            // Get POST values - try both JSON and form data
            $rawBody = $request->getRawBody();
            $jsonData = json_decode($rawBody, true);

            if ($jsonData) {
                $katyel_id = $jsonData['katyel_id'] ?? null;
                $katyel_name = $jsonData['katyel_name'] ?? null;
                $temp = $jsonData['temp'] ?? null;
            } else {
                // Form data request
                $katyel_id = $request->post('katyel_id');
                $katyel_name = $request->post('katyel_name');
                $temp = $request->post('temp');
            }

            // Validate required fields
            if (empty($katyel_id) || empty($katyel_name) || $temp === null || $temp === '') {
                throw new ServerErrorHttpException(
                    'Missing required fields. Received: katyel_id=' . var_export($katyel_id, true) .
                    ', katyel_name=' . var_export($katyel_name, true) .
                    ', temp=' . var_export($temp, true)
                );
            }

            // First, handle payload table (if it exists)
            $payloadSaved = false;
            $payloadId = null;

            // Check if payload table exists
            $tables = Yii::$app->db->schema->getTableNames();
            $payloadTableExists = in_array('payload', $tables) || in_array('payloads', $tables);

            if ($payloadTableExists) {
                try {
                    // Try to save to payload table using raw SQL
                    $result = Yii::$app->db->createCommand(
                        "INSERT INTO payload (katyel_id, katyel_name, temp, datetime)
                         VALUES (:katyel_id, :katyel_name, :temp, :datetime)"
                    )->bindValues([
                        ':katyel_id' => $katyel_id,
                        ':katyel_name' => $katyel_name,
                        ':temp' => $temp,
                        ':datetime' => $currentDateTime
                    ])->execute();

                    $payloadId = Yii::$app->db->getLastInsertID();
                    $payloadSaved = true;

                } catch (\Exception $e) {
                    // Log the error but continue to save to katyel_last_updated
                    Yii::warning('Failed to save to payload table: ' . $e->getMessage(), 'payload');
                }
            }
            try {
                // Check if record exists
                $existingRecord = Yii::$app->db->createCommand(
                    "SELECT id FROM katyel_last_updated WHERE katyel_id = :katyel_id"
                )->bindValue(':katyel_id', $katyel_id)
                    ->queryOne();

                if ($existingRecord) {
                    $result = Yii::$app->db->createCommand(
                        "UPDATE katyel_last_updated
                         SET katyel_name = :katyel_name,
                             temp = :temp,
                             last_updated_at = :last_updated_at
                         WHERE katyel_id = :katyel_id"
                    )->bindValues([
                        ':katyel_id' => $katyel_id,
                        ':katyel_name' => $katyel_name,
                        ':temp' => $temp,
                        ':last_updated_at' => $currentDateTime
                    ])->execute();

                    $katyelUpdated = ($result > 0);

                } else {
                    $result = Yii::$app->db->createCommand(
                        "INSERT INTO katyel_last_updated (katyel_id, katyel_name, temp, last_updated_at)
                         VALUES (:katyel_id, :katyel_name, :temp, :last_updated_at)"
                    )->bindValues([
                        ':katyel_id' => $katyel_id,
                        ':katyel_name' => $katyel_name,
                        ':temp' => $temp,
                        ':last_updated_at' => $currentDateTime
                    ])->execute();

                    $katyelUpdated = ($result > 0);
                }

            } catch (\Exception $e) {
                Yii::error('Failed to update katyel_last_updated: ' . $e->getMessage(), 'payload');
                throw new ServerErrorHttpException('Failed to update katyel_last_updated: ' . $e->getMessage());
            }
            $verifyRecord = Yii::$app->db->createCommand(
                "SELECT * FROM katyel_last_updated WHERE katyel_id = :katyel_id"
            )->bindValue(':katyel_id', $katyel_id)
                ->queryOne();

            if (!$verifyRecord) {
                throw new ServerErrorHttpException('katyel_last_updated record was not found after insert/update operation');
            }

            Yii::$app->response->statusCode = 201;

            return [
                'success' => true,
                'message' => 'Data processed successfully',
                'payload_saved' => $payloadSaved,
                'katyel_updated' => $katyelUpdated,
                'data' => [
                    'payload_id' => $payloadId,
                    'katyel_id' => $katyel_id,
                    'katyel_name' => $katyel_name,
                    'temp' => $temp,
                    'datetime' => $currentDateTime
                ],
                'katyel_last_updated' => $verifyRecord
            ];

        } catch (\Exception $e) {
            Yii::error('Error in actionCreate: ' . $e->getMessage(), 'payload');
            throw new ServerErrorHttpException($e->getMessage());
        }
    }

    // Alternative simplified version using UPSERT
    public function actionCreateSimple()
    {
        $request = Yii::$app->request;

        // Get POST values - try both JSON and form data
        $rawBody = $request->getRawBody();
        $jsonData = json_decode($rawBody, true);

        if ($jsonData) {
            $katyel_id = $jsonData['katyel_id'] ?? null;
            $katyel_name = $jsonData['katyel_name'] ?? null;
            $temp = $jsonData['temp'] ?? null;
        } else {
            $katyel_id = $request->post('katyel_id');
            $katyel_name = $request->post('katyel_name');
            $temp = $request->post('temp');
        }

        $currentDateTime = date('Y-m-d H:i:s');

        if (empty($katyel_id) || empty($katyel_name) || $temp === null) {
            throw new ServerErrorHttpException('Missing required fields');
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE (MySQL)
        $result = Yii::$app->db->createCommand(
            "INSERT INTO katyel_last_updated (katyel_id, katyel_name, temp, last_updated_at)
             VALUES (:katyel_id, :katyel_name, :temp, :last_updated_at)
             ON DUPLICATE KEY UPDATE
                katyel_name = VALUES(katyel_name),
                temp = VALUES(temp),
                last_updated_at = VALUES(last_updated_at)"
        )->bindValues([
            ':katyel_id' => $katyel_id,
            ':katyel_name' => $katyel_name,
            ':temp' => $temp,
            ':last_updated_at' => $currentDateTime
        ])->execute();

        return [
            'success' => true,
            'rows_affected' => $result,
            'data' => [
                'katyel_id' => $katyel_id,
                'katyel_name' => $katyel_name,
                'temp' => $temp,
                'last_updated_at' => $currentDateTime
            ]
        ];
    }

    // Debug action to see what data is being received
    public function actionDebug()
    {
        $request = Yii::$app->request;

        return [
            'post_data' => $request->post(),
            'raw_body' => $request->getRawBody(),
            'json_decoded' => json_decode($request->getRawBody(), true),
            'headers' => $request->headers->toArray(),
            'method' => $request->method,
            'content_type' => $request->contentType
        ];
    }
}
