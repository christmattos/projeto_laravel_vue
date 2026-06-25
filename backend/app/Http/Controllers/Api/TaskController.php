<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Models\TaskImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        return Task::with(['images' => fn($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'done' => 'nullable|boolean',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        return DB::transaction(function () use ($request) {
            Task::query()->increment('position');

            $task = Task::create([
                'title' => $request->title,
                'done' => false,
                'position' => 1,
            ]);

            if ($request->hasFile('images')) {
                $this->attachImages($task, $request->file('images'));
            }

            return response()->json($task->load('images'), 201);
        });
}
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $task = Task::findOrFail($id);

        $task->update([
            'title' => $request->title,
            'done' => $request->done ?? $task->done
        ]);

        if ($request->hasFile('images')) {
            $lastPosition = (int) $task->images()->max('position') ?? 0;
            $this->attachImages($task, $request->file('images'), $lastPosition);
        }

        return response()->json($task->load('images'));
    }

    public function destroy($id)
    {
        $task = Task::with('images')->findOrFail($id);
        
        $this->deleteTaskImages($task);
        $task->delete();
        
        return response()->json(['message' => 'task deletada']);
    }

    public function deleteImage($id)
    {
        $image = TaskImage::findOrFail($id);
        
        if (Storage::disk('public')->exists($image->image)) {
            Storage::disk('public')->delete($image->image);
        }
        
        $image->delete();
        
        return response()->json(['message' => 'imagem deletada']);
    }

    public function reorder(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->positions as $item) {
                Task::where('id', $item['id'])->update(['position' => $item['position']]);
            }
        });

        return response()->json(['message' => 'ordem atualizada']);
    }

    public function reorderImages(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->positions as $item) {
                TaskImage::where('id', $item['id'])->update([
                    'task_id' => $item['task_id'],
                    'position' => $item['position']
                ]);
            }
        });

        return response()->json(['message' => 'imagens reordenadas']);
    }

    private function attachImages(Task $task, $images, int $startPosition = 0): void
    {
        collect($images)->each(function ($image, $index) use ($task, $startPosition) {
            $path = $image->store('tasks', 'public');
            
            TaskImage::create([
                'task_id' => $task->id,
                'image' => $path,
                'position' => $startPosition + $index + 1
            ]);
        });
    }

    private function deleteTaskImages(Task $task): void
    {
        $task->images->each(function ($image) {
            if (Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }
            $image->delete();
        });
    }

}