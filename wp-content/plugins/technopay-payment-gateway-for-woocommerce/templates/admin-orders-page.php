<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tpfw-orders-page" dir="rtl">
	<?php if ( ! empty( $view['notice'] ) ) : ?>
		<div class="tpfw-orders-notice tpfw-orders-notice--<?php echo esc_attr( $view['notice']['type'] ); ?>" role="<?php echo esc_attr( 'error' === $view['notice']['type'] ? 'alert' : 'status' ); ?>">
			<p><?php echo esc_html( $view['notice']['message'] ); ?></p>
			<button type="button" class="tpfw-orders-notice__dismiss" data-orders-notice-dismiss aria-label="بستن پیام"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $view['error'] ) : ?>
		<div class="tpfw-orders-notice tpfw-orders-notice--error" role="alert">
			<p><?php echo esc_html( $view['error'] ); ?></p>
			<button type="button" class="tpfw-orders-notice__dismiss" data-orders-notice-dismiss aria-label="بستن پیام"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
	<?php endif; ?>

	<header class="tpfw-orders-page__header">
		<img src="<?php echo esc_url( TPFW_PLUGIN_URL . 'assets/images/technopay-logo.svg' ); ?>" alt="" class="tpfw-orders-page__logo">
		<div>
			<h1>استرداد سفارشات آنلاین تکنوپی</h1>
			<span class="tpfw-orders-page__count">تعداد نتایج این صفحه: <?php echo esc_html( (string) $view['visible_results'] ); ?></span>
		</div>
	</header>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tpfw-orders-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( TPFW_Admin_Orders_Page::PAGE_SLUG ); ?>">

		<label class="tpfw-orders-field">
			<span>شماره تماس کاربر</span>
			<input type="text" name="customer_mobile" value="<?php echo esc_attr( $view['filters']['customer_mobile'] ); ?>" inputmode="tel" autocomplete="off" placeholder="09121234567">
		</label>

		<div class="tpfw-orders-field">
			<label for="tpfw-orders-amount">مبلغ پرداخت</label>
			<div class="tpfw-orders-field__money">
				<input id="tpfw-orders-amount" type="text" name="amount" value="<?php echo esc_attr( $view['filters']['amount'] ); ?>" inputmode="numeric" autocomplete="off" placeholder="مبلغ دقیق" data-filter-amount>
				<span>تومان</span>
			</div>
		</div>

		<label class="tpfw-orders-field">
			<span>وضعیت</span>
			<select name="order_status">
				<option value="">همه وضعیت‌ها</option>
				<?php foreach ( $view['status_options'] as $tpfw_status_key => $tpfw_status_label ) : ?>
					<option value="<?php echo esc_attr( $tpfw_status_key ); ?>" <?php selected( $view['filters']['status'], $tpfw_status_key ); ?>><?php echo esc_html( $tpfw_status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="tpfw-orders-field">
			<span>بازه زمانی ثبت سفارش</span>
			<select name="order_period">
				<option value="" <?php selected( $view['filters']['period'], '' ); ?>>همه بازه‌ها</option>
				<option value="today" <?php selected( $view['filters']['period'], 'today' ); ?>>امروز</option>
				<option value="yesterday" <?php selected( $view['filters']['period'], 'yesterday' ); ?>>دیروز</option>
				<option value="last-7-days" <?php selected( $view['filters']['period'], 'last-7-days' ); ?>>7 روز گذشته</option>
				<option value="last-30-days" <?php selected( $view['filters']['period'], 'last-30-days' ); ?>>30 روز گذشته</option>
				<option value="current-month" <?php selected( $view['filters']['period'], 'current-month' ); ?>>ماه جاری</option>
			</select>
		</label>

		<div class="tpfw-orders-filters__actions">
			<button type="submit" class="button tpfw-button tpfw-button--primary">
				مشاهده
			</button>
			<a href="<?php echo esc_url( $view['reset_url'] ); ?>" class="tpfw-orders-reset">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				حذف فیلتر ها
			</a>
		</div>
	</form>

	<?php if ( '' === $view['error'] ) : ?>
	<div class="tpfw-orders-table-wrap">
		<table class="tpfw-orders-table">
			<thead>
				<tr>
					<th scope="col">ردیف</th>
					<th scope="col">نام و نام خانوادگی کاربر</th>
					<th scope="col">شماره تماس کاربر</th>
					<th scope="col">شناسه پرداخت</th>
					<th scope="col">تاریخ ثبت پرداخت</th>
					<th scope="col">مبلغ پرداخت</th>
					<th scope="col">مبلغ استرداد</th>
					<th scope="col">وضعیت</th>
					<th scope="col">اقدامات</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $view['rows'] ) ) : ?>
					<tr>
						<td colspan="9" class="tpfw-orders-table__empty">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							سفارشی مطابق با این فیلترها پیدا نشد.
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $view['rows'] as $tpfw_row ) : ?>
						<tr>
							<td data-label="ردیف"><?php echo esc_html( $tpfw_row['number'] ); ?></td>
							<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر"><?php echo esc_html( $tpfw_row['customer_name'] ); ?></td>
							<td data-label="شماره تماس کاربر">
								<div class="tpfw-copy-value">
									<span dir="ltr"><?php echo '' !== $tpfw_row['customer_mobile'] ? esc_html( $tpfw_row['customer_mobile'] ) : '—'; ?></span>
									<?php if ( '' !== $tpfw_row['customer_mobile'] ) : ?>
										<button type="button" class="tpfw-copy-button" data-copy="<?php echo esc_attr( $tpfw_row['customer_mobile'] ); ?>" aria-label="کپی شماره تماس" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
									<?php endif; ?>
								</div>
							</td>
							<td data-label="شناسه پرداخت">
								<div class="tpfw-copy-value">
									<span dir="ltr"><?php echo '' !== $tpfw_row['track_number'] ? esc_html( $tpfw_row['track_number'] ) : '—'; ?></span>
									<?php if ( '' !== $tpfw_row['track_number'] ) : ?>
										<button type="button" class="tpfw-copy-button" data-copy="<?php echo esc_attr( $tpfw_row['track_number'] ); ?>" aria-label="کپی شناسه پرداخت" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
									<?php endif; ?>
								</div>
							</td>
							<td data-label="تاریخ ثبت پرداخت"><?php echo esc_html( $tpfw_row['paid_at'] ); ?></td>
							<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت"><?php echo esc_html( $tpfw_row['amount'] ); ?></td>
							<td class="tpfw-orders-table__money" data-label="مبلغ استرداد"><?php echo esc_html( $tpfw_row['refund_amount'] ); ?></td>
							<td data-label="وضعیت"><span class="tpfw-status tpfw-status--<?php echo esc_attr( $tpfw_row['status_tone'] ); ?>"><?php echo esc_html( $tpfw_row['status_label'] ); ?></span></td>
							<td data-label="اقدامات">
								<?php if ( 'refund' === $tpfw_row['action'] ) : ?>
									<button type="button" class="tpfw-order-action tpfw-order-action--refund" data-refund-modal data-track-number="<?php echo esc_attr( $tpfw_row['track_number'] ); ?>" data-available-amount="<?php echo esc_attr( $tpfw_row['ticket_amount_raw'] ); ?>"><span class="dashicons dashicons-undo" aria-hidden="true"></span>استرداد پرداخت</button>
								<?php elseif ( 'cancel' === $tpfw_row['action'] ) : ?>
									<button type="button" class="tpfw-order-action tpfw-order-action--cancel" data-cancel-refund-modal data-track-number="<?php echo esc_attr( $tpfw_row['track_number'] ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span>لغو درخواست ریفاند</button>
								<?php elseif ( 'details' === $tpfw_row['action'] ) : ?>
									<button type="button" class="tpfw-order-action tpfw-order-action--details" data-details-modal data-refund-reason="<?php echo esc_attr( $tpfw_row['refund_reason'] ); ?>" data-refund-reason-text="<?php echo esc_attr( $tpfw_row['refund_reason_text'] ); ?>" aria-label="مشاهده جزئیات" title="مشاهده جزئیات">
										<img src="<?php echo esc_url( TPFW_PLUGIN_URL . 'assets/images/info-icon-svgrepo-com.svg' ); ?>" alt="" aria-hidden="true">
									</button>
								<?php else : ?>
									<span aria-hidden="true">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ( '' !== $view['pagination']['previous_url'] || '' !== $view['pagination']['next_url'] ) : ?>
		<nav class="tpfw-orders-pagination" aria-label="صفحه‌بندی سفارش‌ها">
			<ul class="page-numbers">
				<?php if ( '' !== $view['pagination']['previous_url'] ) : ?>
					<li><a href="<?php echo esc_url( $view['pagination']['previous_url'] ); ?>" class="page-numbers"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>قبلی</a></li>
				<?php endif; ?>
				<?php if ( '' !== $view['pagination']['next_url'] ) : ?>
					<li><a href="<?php echo esc_url( $view['pagination']['next_url'] ); ?>" class="page-numbers">بعدی<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a></li>
				<?php endif; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<div class="tpfw-refund-modal" role="dialog" aria-modal="true" aria-labelledby="tpfw-refund-modal-title" aria-hidden="true" hidden>
		<div class="tpfw-refund-modal__panel">
			<button type="button" class="tpfw-refund-modal__close" data-refund-modal-close aria-label="بستن"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			<div class="tpfw-refund-modal__visual" aria-hidden="true"><span class="dashicons dashicons-warning"></span></div>
			<h2 id="tpfw-refund-modal-title">ثبت درخواست استرداد پرداخت</h2>
			<p>شما می‌توانید تمام یا بخشی از مبلغ این سفارش را استرداد کنید. این امکان تا 7 روز پس از تأیید سفارش در دسترس است.</p>
			<p>مبلغ موردنظر برای استرداد را در این بخش وارد کنید و دلیل استرداد وجه را نیز ثبت نمایید.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpfw-refund-modal__form" data-refund-form>
				<input type="hidden" name="action" value="tpfw_create_refund">
				<input type="hidden" name="track_number" value="">
				<?php wp_nonce_field( 'tpfw_create_refund', 'tpfw_refund_nonce' ); ?>
				<div class="tpfw-refund-modal__field tpfw-refund-modal__amount">
					<span>مبلغ:</span>
					<input type="text" name="requested_amount" inputmode="numeric" autocomplete="off" placeholder="-------" aria-label="مبلغ استرداد" required>
					<span>تومان</span>
					<button type="button" class="tpfw-refund-modal__full-amount" data-refund-full-amount>کل مبلغ</button>
				</div>
				<label class="tpfw-refund-modal__field tpfw-refund-modal__reason-field">
					<span>دلیل استرداد:</span>
					<select name="refund_reason" class="tpfw-refund-modal__reason" aria-label="دلیل استرداد" aria-controls="tpfw-refund-custom-reason" aria-expanded="false" required>
						<option value="returned-order">مرجوع سفارش</option>
						<option value="customer-cancellation">انصراف مشتری</option>
						<option value="payment-error">خطا در پرداخت</option>
						<option value="other">سایر</option>
					</select>
				</label>
				<label id="tpfw-refund-custom-reason" class="tpfw-refund-modal__field tpfw-refund-modal__custom-reason" hidden>
					<span>دلیل:</span>
					<input type="text" name="custom_refund_reason" autocomplete="off" placeholder="دلیل استرداد را وارد کنید" aria-label="دلیل استرداد سفارشی">
				</label>
				<div class="tpfw-refund-modal__actions">
					<button type="button" class="tpfw-refund-modal__cancel" data-refund-modal-close>فعلا نه</button>
					<button type="submit" class="tpfw-refund-modal__submit">ثبت درخواست</button>
				</div>
			</form>
		</div>
	</div>

	<div class="tpfw-refund-modal tpfw-cancel-modal" role="dialog" aria-modal="true" aria-labelledby="tpfw-cancel-modal-title" aria-hidden="true" hidden>
		<div class="tpfw-refund-modal__panel">
			<button type="button" class="tpfw-refund-modal__close" data-refund-modal-close aria-label="بستن"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			<div class="tpfw-refund-modal__visual tpfw-refund-modal__visual--cancel" aria-hidden="true"><span class="dashicons dashicons-warning"></span></div>
			<h2 id="tpfw-cancel-modal-title">لغو درخواست استرداد</h2>
			<p>در صورت تأیید، درخواست استرداد ثبت‌شده توسط شما لغو خواهد شد. پس از آن می‌توانید مجددا درخواست استرداد وجه برای این پرداخت ثبت نمایید.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpfw-refund-modal__form tpfw-cancel-modal__form" data-cancel-refund-form>
				<input type="hidden" name="action" value="tpfw_cancel_refund">
				<input type="hidden" name="track_number" value="">
				<?php wp_nonce_field( 'tpfw_cancel_refund', 'tpfw_cancel_refund_nonce' ); ?>
				<div class="tpfw-refund-modal__actions">
					<button type="button" class="tpfw-refund-modal__cancel" data-refund-modal-close>فعلا نه</button>
					<button type="submit" class="tpfw-refund-modal__submit">بله مطمئن هستم</button>
				</div>
			</form>
		</div>
	</div>

	<div class="tpfw-refund-modal tpfw-details-modal" role="dialog" aria-modal="true" aria-labelledby="tpfw-details-modal-title" aria-hidden="true" hidden>
		<div class="tpfw-refund-modal__panel">
			<button type="button" class="tpfw-refund-modal__close" data-refund-modal-close aria-label="بستن"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			<h2 id="tpfw-details-modal-title">دلایل استرداد</h2>
			<p>در بخش زیر می‌توانید دلایل استرداد پرداخت و یا دلایل رد درخواست استرداد را مشاهده کنید</p>
			<div class="tpfw-details-modal__fields">
				<div class="tpfw-refund-modal__field">
					<span>دلیل استرداد:</span>
					<span data-details-reason></span>
				</div>
				<div class="tpfw-refund-modal__field" data-details-custom-row hidden>
					<span>توضیحات:</span>
					<span data-details-reason-text></span>
				</div>
			</div>
			<div class="tpfw-refund-modal__actions tpfw-details-modal__actions">
				<button type="button" class="tpfw-refund-modal__submit" data-refund-modal-close>متوجه شدم</button>
			</div>
		</div>
	</div>
</div>
