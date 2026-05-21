<?php

namespace App\Services;

use App\Models\Google\GoogleForm;
use App\Models\Google\GoogleFormResponse;
use App\Models\Google\GoogleFormStat;
use Google\Client;
use Google\Service\Forms;
use Google\Service\Forms\BatchUpdateFormRequest;
use Google\Service\Forms\ChoiceQuestion;
use Google\Service\Forms\CreateItemRequest;
use Google\Service\Forms\DateQuestion;
use Google\Service\Forms\DeleteItemRequest;
use Google\Service\Forms\Form;
use Google\Service\Forms\Info;
use Google\Service\Forms\Item;
use Google\Service\Forms\Location;
use Google\Service\Forms\Option;
use Google\Service\Forms\Question;
use Google\Service\Forms\QuestionItem;
use Google\Service\Forms\Request as GoogleRequest;
use Google\Service\Forms\TextQuestion;
use Google\Service\Forms\TimeQuestion;
use Google\Service\Forms\UpdateFormInfoRequest;
use Illuminate\Support\Facades\Log;

/**
 * Google API 服務
 */
class GoogleApiService
{
    protected GoogleOAuthService $oauth;

    protected ?Client $client = null;

    public function __construct(GoogleOAuthService $oauth)
    {
        $this->oauth = $oauth;
    }

    /**
     * 取得已授權的 Google\Client(每次呼叫都會檢查 / 自動 refresh token)。
     *
     * @throws \RuntimeException 當系統尚未完成 OAuth 授權時
     */
    protected function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }
        $this->client = $this->oauth->getAuthorizedClient();

        return $this->client;
    }

    public function extractFormId($url)
    {
        if (preg_match('/forms\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            if (str_starts_with($matches[1], '1FAIp') || str_starts_with($matches[1], 'e/1FAIp')) {
                return null;
            }

            return preg_replace('/^e\//', '', $matches[1]);
        }

        return null;
    }

    public function getFormDetails($formId)
    {
        try {
            $service = new Forms($this->client());
            $form = $service->forms->get($formId);

            return [
                'status' => true,
                'data' => $form,
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms Get Details Failed: '.$e->getMessage());

            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function getFormSummary($formId)
    {
        try {
            $service = new Forms($this->client());
            $responses = $service->forms_responses->listFormsResponses($formId);
            $responsesList = $responses->getResponses() ?? [];
            $formBody = $service->forms->get($formId);

            return [
                'status' => true,
                'title' => $formBody->getInfo()->getTitle(),
                'response_count' => count($responsesList),
                'responses' => $responsesList,
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms API Failed: '.$e->getMessage());

            return ['status' => false, 'error' => '無法讀取表單，這通常是因為服務帳戶沒有權限。'];
        }
    }

    public function createForm($title)
    {
        try {
            $service = new Forms($this->client());
            $formBody = new Form;
            $info = new Info;
            $info->setTitle($title);
            $formBody->setInfo($info);
            $createdForm = $service->forms->create($formBody);

            return [
                'status' => true,
                'form_id' => $createdForm->getFormId(),
                'responder_uri' => $createdForm->getResponderUri(),
                'edit_url' => "https://docs.google.com/forms/d/{$createdForm->getFormId()}/edit",
                'title' => $createdForm->getInfo()->getTitle(),
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms Create Failed: '.$e->getMessage());

            return ['status' => false, 'error' => '建立表單失敗：'.$e->getMessage()];
        }
    }

    public function syncFormItems($formId, $questions, $title = null, $description = null)
    {
        try {
            $service = new Forms($this->client());
            $form = $service->forms->get($formId);
            $currentItems = $form->getItems() ?? [];
            $requests = [];

            foreach ($currentItems as $item) {
                $deleteRequest = new GoogleRequest;
                $deleteItem = new DeleteItemRequest;
                $location = new Location;
                $location->setIndex(0);
                $deleteItem->setLocation($location);
                $deleteRequest->setDeleteItem($deleteItem);
                $requests[] = $deleteRequest;
            }

            if ($title || $description) {
                $updateInfo = new UpdateFormInfoRequest;
                $info = new Info;
                if ($title) {
                    $info->setTitle($title);
                }
                if ($description) {
                    $info->setDescription($description);
                }
                $updateInfo->setInfo($info);
                $mask = [];
                if ($title) {
                    $mask[] = 'title';
                }
                if ($description) {
                    $mask[] = 'description';
                }
                $updateInfo->setUpdateMask(implode(',', $mask));
                $req = new GoogleRequest;
                $req->setUpdateFormInfo($updateInfo);
                $requests[] = $req;
            }

            $index = 0;
            foreach ($questions as $q) {
                $requests[] = $this->buildCreateItemRequest($q, $index);
                $index++;
            }

            if (! empty($requests)) {
                $batchRequest = new BatchUpdateFormRequest;
                $batchRequest->setRequests($requests);
                $service->forms->batchUpdate($formId, $batchRequest);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Google Forms Sync Failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function buildCreateItemRequest($q, $index)
    {
        $questionData = new Question;
        $questionData->setRequired(isset($q['required']) ? (bool) $q['required'] : false);
        $type = $q['type'] ?? 'text';

        switch (strtolower($type)) {
            case 'radio':
            case 'checkbox':
            case 'drop_down':
            case 'dropdown':
                $options = [];
                foreach ($q['options'] ?? [] as $opt) {
                    $val = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                    if ($val === null || $val === '') {
                        continue;
                    }
                    $optionItem = new Option;
                    $optionItem->setValue((string) $val);
                    $options[] = $optionItem;
                }
                $mappedType = strtoupper($type === 'dropdown' || $type === 'drop_down' ? 'DROP_DOWN' : $type);
                $choiceQuestion = new ChoiceQuestion;
                $choiceQuestion->setType($mappedType);
                $choiceQuestion->setOptions($options);
                $questionData->setChoiceQuestion($choiceQuestion);
                break;
            case 'date':
                $questionData->setDateQuestion(new DateQuestion);
                break;
            case 'time':
                $questionData->setTimeQuestion(new TimeQuestion);
                break;
            case 'short text':
            case 'long text':
            case 'text':
            default:
                $textQuestion = new TextQuestion;
                $textQuestion->setParagraph(strtolower($type) === 'long text');
                $questionData->setTextQuestion($textQuestion);
                break;
        }

        $qi = new QuestionItem;
        $qi->setQuestion($questionData);
        $item = new Item;
        $item->setTitle($q['label'] ?? '新問題');
        $item->setQuestionItem($qi);

        $createItem = new CreateItemRequest;
        $createItem->setItem($item);
        $location = new Location;
        $location->setIndex($index);
        $createItem->setLocation($location);

        $req = new GoogleRequest;
        $req->setCreateItem($createItem);

        return $req;
    }

    public function batchUpdateQuestions($formId, $questions, $description = null)
    {
        try {
            $service = new Forms($this->client());
            $requests = [];
            if ($description) {
                $updateInfo = new UpdateFormInfoRequest;
                $info = new Info;
                $info->setDescription($description);
                $updateInfo->setInfo($info);
                $updateInfo->setUpdateMask('description');
                $req = new GoogleRequest;
                $req->setUpdateFormInfo($updateInfo);
                $requests[] = $req;
            }
            $index = 0;
            foreach ($questions as $q) {
                $requests[] = $this->buildCreateItemRequest($q, $index);
                $index++;
            }
            if (! empty($requests)) {
                $batchRequest = new BatchUpdateFormRequest;
                $batchRequest->setRequests($requests);
                $service->forms->batchUpdate($formId, $batchRequest);
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getFormFillList($formId)
    {
        try {
            $service = new Forms($this->client());
            $responses = $service->forms_responses->listFormsResponses($formId);
            $responsesList = $responses->getResponses() ?? [];
            $formBody = $service->forms->get($formId);

            // 建立 questionId 與題目 (Title) 的映射表
            $questionMap = [];
            if ($formBody->getItems()) {
                foreach ($formBody->getItems() as $item) {
                    if ($item->getQuestionItem()) {
                        $question = $item->getQuestionItem()->getQuestion();
                        if ($question) {
                            $questionMap[$question->getQuestionId()] = $item->getTitle();
                        }
                    }
                }
            }

            // 格式化回覆清單
            $formattedResponses = [];
            foreach ($responsesList as $response) {
                $answers = $response->getAnswers() ?? [];
                $formattedAnswers = [];

                foreach ($answers as $questionId => $answer) {
                    $questionTitle = $questionMap[$questionId] ?? '未知題目';
                    $answerText = '';

                    if ($answer->getTextAnswers() && $answer->getTextAnswers()->getAnswers()) {
                        $texts = [];
                        foreach ($answer->getTextAnswers()->getAnswers() as $textAnswer) {
                            $texts[] = $textAnswer->getValue();
                        }
                        $answerText = implode(', ', $texts);
                    }

                    $formattedAnswers[] = [
                        'questionId' => $questionId,
                        'title' => $questionTitle,
                        'answer' => $answerText,
                    ];
                }

                $formattedResponses[] = [
                    'responseId' => $response->getResponseId(),
                    'createTime' => $response->getCreateTime(),
                    'lastSubmittedTime' => $response->getLastSubmittedTime(),
                    'answers' => $formattedAnswers,
                ];
            }

            return [
                'status' => true,
                'title' => $formBody->getInfo()->getTitle(),
                'response_count' => count($responsesList),
                'responses' => $formattedResponses,
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms API Fill List Failed: '.$e->getMessage());

            return ['status' => false, 'error' => '無法讀取表單填寫狀況：'.$e->getMessage()];
        }
    }

    public function syncFormFills($googleFormId)
    {
        $googleForm = GoogleForm::find($googleFormId);
        if (! $googleForm) {
            return ['status' => false, 'error' => '找不到該筆 Google 表單紀錄'];
        }

        $googleResult = $this->getFormFillList($googleForm->form_id);
        if ($googleResult['status'] === true) {
            $responses = $googleResult['responses'];
            $responseCount = count($responses);

            // 取得活動的審核設定
            $event = $googleForm->event;
            $defaultStatus = ($event && $event->is_approve == 0) ? 1 : 0;

            foreach ($responses as $resp) {
                $existing = GoogleFormResponse::where('google_response_id', $resp['responseId'])->first();

                $data = [
                    'event_id' => $googleForm->event_id,
                    'google_form_id' => $googleForm->id,
                    'answers' => $resp['answers'],
                    'submitted_at' => ! empty($resp['createTime']) ? date('Y-m-d H:i:s', strtotime($resp['createTime'])) : null,
                ];

                // 只有在新建資料時才判定初始狀態
                if (! $existing) {
                    $data['status'] = $defaultStatus; // 根據活動設定決定是待審還是自動通過
                    $data['google_response_id'] = $resp['responseId'];
                    GoogleFormResponse::create($data);
                } else {
                    $existing->update($data);
                }
            }

            $stat = GoogleFormStat::firstOrCreate(
                ['google_form_id' => $googleForm->id],
                ['event_id' => $googleForm->event_id, 'view_count' => 0, 'response_count' => 0]
            );
            $stat->response_count = $responseCount;
            $stat->save();

            return ['status' => true, 'synced_count' => $responseCount];
        }

        return $googleResult;
    }
}
