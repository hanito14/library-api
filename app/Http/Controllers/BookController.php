<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReviews;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookController extends Controller
{
    public function BookController()
    {
    }

    public function index()
    {
        $books = Book::with("authors", 'category', 'editorial')->get();

        return response()->json([
            "status" => ($books) ? true : false,
            "message" => "Success",
            "data" => $books
        ]);
    }

    public function store(Request $request)
    {
        $response = $this->getResponse();
        DB::beginTransaction();
        try {
            $existBook = Book::where("isbn", trim($request->isbn))->exists();
            if (!$existBook) {
                $book = new Book();
                $book->isbn = trim($request->isbn);
                $book->title = $request->title;
                $book->description = $request->description;
                $book->category_id = $request->category["id"];
                $book->editorial_id = $request->editorial["id"];
                $book->publish_date = Carbon::now();
                $book->save();

                foreach ($request->authors as $item) {
                    $book->authors()->attach($item);
                }

                $book = Book::with("authors", 'category', 'editorial')->where('isbn', trim($request->isbn))->get();

                $response["status"] = true;
                $response["message"] = "Success";
                $response["data"] = $book;
            } else {
                $response["message"] = "ISBN duplicado";
            }
            DB::commit();
        } catch (Exception $err) {
            DB::rollBack();
            $response["status"] = false;
            $response["message"] = "Error" + $err->getMessage();
        }
        return $response;
    }

    public function update(Request $request, $id)
    {
        $response = $this->getResponse();

        DB::beginTransaction();
        try {
            $book = Book::find($id);
            if ($book) {
                $existBook = Book::where("isbn", trim($request->isbn))->where('id', '<>', $id)->exists();
                if (!$existBook) {
                    $book->isbn = trim($request->isbn);
                    $book->title = $request->title;
                    $book->description = $request->description;
                    $book->category_id = $request->category["id"];
                    $book->editorial_id = $request->editorial["id"];
                    $book->publish_date = Carbon::now();

                    foreach ($book->authors as $item) {
                        $book->authors()->detach($item->id);
                    }
                    $book->update();
                    if ($request->authors) {
                        foreach ($request->authors as $item) {
                            $book->authors()->attach($item);
                        }
                    }
                    $book = Book::with("authors", 'category', 'editorial')->where('id', $id)->get();

                    $response["status"] = true;
                    $response["message"] = "Success";
                    $response["data"] = $book;
                } else {
                    $response["message"] = "ISBN duplicado";
                }
            } else {
                $response["message"] = "Error, no se encontró";
            }
            DB::commit();
        } catch (Exception $err) {
            DB::rollBack();
            $response["status"] = false;
            $response["message"] = "Error" + $err->getMessage();
        }
        return $response;
    }

    public function find($id)
    {
        $response = $this->getResponse();

        $book = Book::with("authors", 'category', 'editorial')->where('id', $id)->first();

        if ($book) {
            $response["status"] = true;
            $response["message"] = "Success";
            $response["data"] = $book;
        } else {
            $response["message"] = "Error, no se encontró";
        }
        return  $response;
    }

    public function delete($id)
    {
        $response = $this->getResponse();

        DB::beginTransaction();
        try {
            $book = Book::find($id);
            if ($book) {
                foreach ($book->authors as $item) {
                    $book->authors()->detach($item->id);
                }

                $book->delete();
                $response["status"] = true;
                $response["message"] = "Success";
            } else {
                $response["message"] = "Error, no se encontró";
            }
            DB::commit();
        } catch (Exception $err) {
            DB::rollBack();
            $response["status"] = false;
            $response["message"] = "Error" + $err->getMessage();
        }
        return $response;
    }

    public function addBookReview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required'
        ]);
        if (!$validator->fails()) {
            DB::beginTransaction();
            try {
                $bookReview = new BookReviews();
                $bookReview->comment = trim($request->comment);
                $bookReview->book_id = $id;
                $bookReview->user_id = auth()->user()->id;
                $bookReview->edited = false;
                $bookReview->save();

                $bookReview = BookReviews::with("book", 'user')
                    ->where('user_id', auth()->user()->id)
                    ->where('book_id', $id)
                    ->first();

                DB::commit();

                return $this->getResponse201(
                    "book review",
                    "created",
                    $bookReview
                );
            } catch (Exception $err) {
                DB::rollBack();
                return $this->getResponse500([$err->getMessage()]);
            }
        } else {
            return $this->getResponse500([$validator->errors()]);
        }
    }

    public function updateBookReview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required'
        ]);
        if (!$validator->fails()) {
            DB::beginTransaction();
            try {
                $bookReview = BookReviews::with("book", 'user')->where('id', $id)->first();
                if ($bookReview) {
                    if ($bookReview->user_id == auth()->user()->id) {
                        $bookReview->comment = trim($request->comment);
                        $bookReview->edited = true;
                        $bookReview->update();

                        $bookReview = BookReviews::with("book", 'user')->where('id', $id)->first();

                        DB::commit();

                        return $this->getResponse201(
                            "book review",
                            "updated",
                            $bookReview
                        );
                    } else {
                        return $this->getResponse403();
                    }
                } else {
                    return $this->getResponse500(["Book review not founded"]);
                }
            } catch (Exception $err) {
                DB::rollBack();
                return $this->getResponse500([$err->getMessage()]);
            }
        } else {
            return $this->getResponse500([$validator->errors()]);
        }
    }
}
