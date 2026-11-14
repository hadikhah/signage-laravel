<?php

namespace App\Services\Playback;

use App\Enums\ContentType;
use App\Services\Playlist\Playlist;
use App\Services\Playlist\PlaylistItem;
use App\Services\Support\PersianDate;


final class PlaybackTransformer
{
	public function transform(Playlist $playlist): array
	{
		$bypass = $playlist->bypassAllContent;

		return [
			'ShareContents'     => $bypass ? array_map([$this, 'itemToArray'], $playlist->items) : null,
			'MonitorContents'   => $bypass ? null : array_map([$this, 'itemToArray'], $playlist->items),
			'DailyMessage'      => $playlist->dailyMessage,
			'IsMonitorContent'  => $playlist->isMonitorContent,
			'ByPassAllContent'  => $playlist->bypassAllContent,
			'MonitorDateTime'   => PersianDate::dateTimeObject(now()),
		];
	}

	public function itemToArray(PlaylistItem $item): array
	{
		$type = ContentType::tryFrom($item->contentType);

		$uploadedAt = $item->fromDate; 

		return [
			'ContentId'        => $item->contentId,
			'ContentName'      => $item->contentName,
			'UserId'           => $item->userId,
			'UserName'         => $item->userName,
			'ContentType'      => $item->contentType,
			'Contentpath'      => $item->contentPath,      // lowercase 'p' per legacy
			'ClientFileName'   => $item->clientFileName,
			'FromDate'         => $item->fromDate?->format('Y-m-d\TH:i:s'),
			'ToDate'           => $item->toDate?->format('Y-m-d\TH:i:s'),
			'ContentTypeText'  => $type?->labelFa(),
			'UploadeDateTime'  => $item->uploadedAt !== null
				? PersianDate::dateTime($item->uploadedAt)
				: null,
			'UploadDate'       => $item->uploadedAt !== null
				? PersianDate::dateOnly($item->uploadedAt)
				: null,
			'UploadTime'       => $item->uploadedAt !== null
				? PersianDate::timeOnly($item->uploadedAt)
				: null,
			'DisplayTime'      => $item->displayTime,
			'Active'           => $item->active,
			'UrlUserName'      => $item->urlUserName,
			'UrlPassword'      => $item->urlPassword,
			'IsAutoLogin'      => $item->isAutoLogin,
			'Url'              => $item->url,
		];
	}
}
