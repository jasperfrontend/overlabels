<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\OverlayTemplate;
use App\Services\FunSlugGenerationService;
use Illuminate\Database\Seeder;

class OverlayTemplateSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();

        if (!$user) {
            return;
        }

        $templates = [
            [
                'name' => 'Simple Follower Alert',
                'description' => 'A basic follower alert template',
                'html' => '<div class="follower-alert">
                    <h1>New Follower!</h1>
                    <p>{{ user.display_name }} just followed!</p>
                    <p>Total followers: {{ user.follower_count | number }}</p>
                </div>',
                'css' => '.follower-alert {
                    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                    animation: slideIn 0.5s ease-out;
                }
                @keyframes slideIn {
                    from { transform: translateX(-100%); }
                    to { transform: translateX(0); }
                }',
                'is_public' => true,
            ],
            [
                'name' => 'Stream Info Bar',
                'description' => 'A information bar showing stream stats',
                'html' => '<div class="info-bar">
                    <div class="info-item">
                        <span class="label">Streaming:</span>
                        <span class="value">{{ stream.game_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Viewers:</span>
                        <span class="value">{{ stream.viewer_count | number }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Followers:</span>
                        <span class="value">{{ user.follower_count | number }}</span>
                    </div>
                </div>',
                'css' => '.info-bar {
                    display: flex;
                    background: rgba(0, 0, 0, 0.8);
                    color: white;
                    padding: 10px;
                    font-family: Arial, sans-serif;
                }
                .info-item {
                    margin-right: 20px;
                }
                .label {
                    color: #a0a0a0;
                    margin-right: 5px;
                }
                .value {
                    font-weight: bold;
                }',
                'is_public' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            OverlayTemplate::create([
                ...$templateData,
                'owner_id' => $user->id,
                'slug' => app(FunSlugGenerationService::class)->generate(),
            ]);
        }
    }
}
