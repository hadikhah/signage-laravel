<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Requests\PaginatedRequest;
use App\Legacy\Envelope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Provides index() (paginated) and list() (flat) for a resource.
 *
 * Both endpoints support:
 *   ?search=<term>          case-insensitive LIKE across searchableColumns()
 *   ?sortBy=<PascalCase>    maps to DB column via sortableColumns()
 *   ?sortDirection=asc|desc
 *
 * Unknown sortBy falls back to id asc rather than erroring —
 * TODO(contract): confirm fallback vs 422.
 */
trait HandlesPaginatedList
{
	abstract protected function baseQuery(): Builder;

	/** @return class-string<\Illuminate\Http\Resources\Json\JsonResource> */
	abstract protected function resourceClass(): string;

	/** @return string[]  DB columns to search with case-insensitive LIKE */
	abstract protected function searchableColumns(): array;

	/** @return array<string, string>  PascalCase field → DB column */
	abstract protected function sortableColumns(): array;

	public function index(PaginatedRequest $request)
	{
		$page = $this->prepareQuery($request)
			->paginate(
				perPage: $request->rowNumber(),
				page: $request->pageIndex(),
			)
			->through(fn($model) => (new ($this->resourceClass())($model))->resolve());

		return response()->json(Envelope::paginated($page));
	}

	public function list(PaginatedRequest $request)
	{
		$items = $this->prepareQuery($request)
			->get()
			->map(fn($model) => (new ($this->resourceClass())($model))->resolve())
			->all();

		return response()->json(Envelope::list($items));
	}

	private function prepareQuery(PaginatedRequest $request): Builder
	{
		$query = $this->baseQuery();
		$this->applySearch($query, $request);
		$this->applySort($query, $request);
		return $query;
	}

	private function applySearch(Builder $query, PaginatedRequest $request): void
	{
		$term = $request->searchTerm();
		if ($term === null) return;

		$columns = $this->searchableColumns();
		if ($columns === []) return;

		// Postgres: ilike for case-insensitive. MySQL/SQLite: like.
		$operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

		// TODO(contract): wildcard escaping — currently % and _ in the term
		// act as wildcards. Acceptable for read-only endpoints; refine if
		// legacy has special chars in searchable data.
		$pattern = '%' . $term . '%';

		$query->where(function (Builder $q) use ($columns, $operator, $pattern) {
			foreach ($columns as $col) {
				$q->orWhere($col, $operator, $pattern);
			}
		});
	}

	private function applySort(Builder $query, PaginatedRequest $request): void
	{
		$sortBy    = $request->sortBy();
		$direction = $request->sortDirection();

		if ($sortBy === null) {
			$query->orderBy('id', 'asc');
			return;
		}

		$column = $this->sortableColumns()[$sortBy] ?? null;

		// Unknown sort key → fall back to default rather than 500.
		if ($column === null) {
			$query->orderBy('id', 'asc');
			return;
		}

		$query->orderBy($column, $direction->value);
	}
}
