<?php

namespace App\Http\Controllers;

use App\Models\LlmProvider;
use App\Services\Llm\LlmClient;
use App\Services\Llm\FreeModels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function __construct(private readonly LlmClient $llm) {}

    public function index(): View
    {
        return view('providers.index', [
            'providers' => LlmProvider::orderByDesc('is_default')->orderBy('id')->get(),
            'categories' => FreeModels::categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'base_url' => ['required', 'url', 'max:200'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'model' => ['nullable', 'string', 'max:120'],
            'supports_tools' => ['nullable', 'boolean'],
        ], [], ['base_url' => 'Адрес API']);

        LlmProvider::create($data + [
            'slug' => 'custom-'.substr(md5($data['base_url'].$data['label']), 0, 8),
            'is_active' => true,
            'supports_tools' => $request->boolean('supports_tools', true),
        ]);

        return back()->with('success', 'Провайдер добавлен');
    }

    public function update(Request $request, LlmProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'base_url' => ['required', 'url', 'max:200'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'model' => ['nullable', 'string', 'max:120'],
            'supports_tools' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [], ['base_url' => 'Адрес API']);

        // Пустой ключ означает «оставить прежний», чтобы не стирать его при редактировании.
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $data['supports_tools'] = $request->boolean('supports_tools');
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($provider, $data, $request): void {
            if ($request->boolean('is_default')) {
                LlmProvider::where('id', '!=', $provider->id)->update(['is_default' => false]);
            }

            $provider->update($data + ['is_default' => $request->boolean('is_default')]);
        });

        return back()->with('success', 'Настройки провайдера сохранены');
    }

    public function destroy(LlmProvider $provider): RedirectResponse
    {
        if (LlmProvider::count() === 1) {
            return back()->with('error', 'Нельзя удалить единственный провайдер');
        }

        $provider->delete();

        return back()->with('success', 'Провайдер удалён');
    }

    /** Проверка доступности модели — вызывается из карточки провайдера. */
    public function probe(LlmProvider $provider): JsonResponse
    {
        $result = $this->llm->probe($provider);

        if ($result['ok'] && $result['models']) {
            $provider->update(['models' => collect($result['models'])
                ->mapWithKeys(fn (string $id) => [$id => $id])
                ->all()]);
        }

        return response()->json($result);
    }

    /** Добавить провайдера из готового пресета бесплатных моделей. */
    public function addPreset(Request $request): RedirectResponse
    {
        $slug = (string) $request->input('slug');
        $preset = config('agents.providers.'.$slug);

        if (! is_array($preset)) {
            return back()->with('error', 'Неизвестный пресет');
        }

        if (LlmProvider::where('slug', $slug)->exists()) {
            return back()->with('error', 'Этот провайдер уже добавлен');
        }

        LlmProvider::create([
            'slug' => $slug,
            'label' => $preset['label'],
            'base_url' => $preset['base_url'],
            'model' => array_key_first($preset['models']),
            'models' => $preset['models'],
            'supports_tools' => $preset['supports_tools'],
            'note' => $preset['note'] ?? null,
            'is_active' => true,
            'is_default' => LlmProvider::count() === 0,
        ]);

        return back()->with('success', 'Провайдер «'.$preset['label'].'» добавлен');
    }
}