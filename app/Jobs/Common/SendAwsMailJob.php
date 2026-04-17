<?php

namespace App\Jobs\Common;

use App\Services\AwsSesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAwsMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mailChunk;

    /**
     * Create a new job instance.
     *
     * @param array $mailChunk 格式: [['email' => '...', 'subject' => '...', 'body' => '...'], ...]
     */
    public function __construct(array $mailChunk)
    {
        $this->mailChunk = $mailChunk;
    }

    /**
     * Execute the job.
     */
    public function handle(AwsSesService $service)
    {
        Log::info("Processing AWS SES Email Job Chunk, count: " . count($this->mailChunk));

        foreach ($this->mailChunk as $data) {
            // 執行 Service 的發信方法
            $service->send(
                $data['email'],
                $data['subject'],
                $data['body'],
                $data['from'] ?? null
            );
        }
    }
}
