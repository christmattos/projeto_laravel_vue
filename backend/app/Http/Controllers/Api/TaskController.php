<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Models\TaskImage;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index()
    {
        return Task::with('images')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'done' => 'nullable|boolean',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $task = Task::create([
            'title' => $request->title,
            'done' => false
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('tasks', 'public');
                TaskImage::create([
                    'task_id' => $task->id,
                    'image' => $path
                ]);
            }
        }
    return response()->json($task->load('images'), 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $task->update([
            'title' => $request->title,
            'done' => $request->done
        ]);
        return $task;
    }

    public function destroy($id)
    {
        $task = Task::with('images')->findOrFail($id);
        foreach ($task->images as $image) {
            if (Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }
            $image->delete();
        }
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
}