<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tpfw-orders-page" dir="rtl">
	<header class="tpfw-orders-page__header">
		<img src="<?php echo esc_url( TPFW_PLUGIN_URL . 'assets/images/technopay-logo.svg' ); ?>" alt="" class="tpfw-orders-page__logo">
		<div>
			<h1>استرداد سفارشات آنلاین تکنوپی</h1>
			<span class="tpfw-orders-page__count">تعداد سفارش‌ها: 5</span>
		</div>
	</header>

	<form class="tpfw-orders-filters">
		<label class="tpfw-orders-field">
			<span>شماره تماس کاربر</span>
			<input type="text" name="customer_mobile" inputmode="tel" autocomplete="off" placeholder="09121234567">
		</label>

		<label class="tpfw-orders-field">
			<span>مبلغ پرداخت</span>
			<input type="text" name="amount" inputmode="decimal" autocomplete="off" placeholder="مبلغ دقیق">
		</label>

		<label class="tpfw-orders-field">
			<span>وضعیت</span>
			<select name="order_status">
				<option value="">همه وضعیت‌ها</option>
				<option value="approved">تایید شده</option>
				<option value="finalized">نهایی شده</option>
				<option value="refunded">استرداد شده</option>
			</select>
		</label>

		<label class="tpfw-orders-field">
			<span>بازه زمانی ثبت سفارش</span>
			<select name="order_period">
				<option value="">همه بازه‌ها</option>
				<option value="today">امروز</option>
				<option value="yesterday">دیروز</option>
				<option value="last-7-days">7 روز گذشته</option>
				<option value="last-30-days">30 روز گذشته</option>
				<option value="current-month">ماه جاری</option>
			</select>
		</label>

		<div class="tpfw-orders-filters__actions">
			<button type="button" class="button tpfw-button tpfw-button--primary">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				مشاهده
			</button>
			<button type="reset" class="tpfw-orders-reset">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				حذف همه
			</button>
		</div>
	</form>

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
				<tr>
					<td data-label="ردیف">1</td>
					<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر">سپهر کیانی</td>
					<td data-label="شماره تماس کاربر">
						<div class="tpfw-copy-value">
							<span dir="ltr">09123339654</span>
							<button type="button" class="tpfw-copy-button" data-copy="09123339654" aria-label="کپی شماره تماس" title="کپی">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
						</div>
					</td>
					<td data-label="شناسه پرداخت">
						<div class="tpfw-copy-value">
							<span dir="ltr">621945456545</span>
							<button type="button" class="tpfw-copy-button" data-copy="621945456545" aria-label="کپی شناسه پرداخت" title="کپی">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
						</div>
					</td>
					<td data-label="تاریخ ثبت پرداخت">1403/02/12</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت">1,666,670 تومان</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ استرداد">—</td>
					<td data-label="وضعیت"><span class="tpfw-status tpfw-status--success">تایید شده</span></td>
					<td data-label="اقدامات">
						<button type="button" class="tpfw-order-action tpfw-order-action--refund" data-refund-modal data-payment-amount="1,666,670">
							<span class="dashicons dashicons-undo" aria-hidden="true"></span>
							استرداد پرداخت
						</button>
					</td>
				</tr>
				<tr>
					<td data-label="ردیف">2</td>
					<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر">سپهر کیانی</td>
					<td data-label="شماره تماس کاربر"><div class="tpfw-copy-value"><span dir="ltr">09123339654</span><button type="button" class="tpfw-copy-button" data-copy="09123339654" aria-label="کپی شماره تماس" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="شناسه پرداخت"><div class="tpfw-copy-value"><span dir="ltr">621945456545</span><button type="button" class="tpfw-copy-button" data-copy="621945456545" aria-label="کپی شناسه پرداخت" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="تاریخ ثبت پرداخت">1403/02/14</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت">1,666,670 تومان</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ استرداد">1,666,670 تومان</td>
					<td data-label="وضعیت"><span class="tpfw-status tpfw-status--warning">در انتظار استرداد پرداخت</span></td>
					<td data-label="اقدامات"><button type="button" class="tpfw-order-action tpfw-order-action--cancel"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span>لغو درخواست ریفاند</button></td>
				</tr>
				<tr>
					<td data-label="ردیف">3</td>
					<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر">سپهر کیانی</td>
					<td data-label="شماره تماس کاربر"><div class="tpfw-copy-value"><span dir="ltr">09123339654</span><button type="button" class="tpfw-copy-button" data-copy="09123339654" aria-label="کپی شماره تماس" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="شناسه پرداخت"><div class="tpfw-copy-value"><span dir="ltr">621945456545</span><button type="button" class="tpfw-copy-button" data-copy="621945456545" aria-label="کپی شناسه پرداخت" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="تاریخ ثبت پرداخت">1403/02/12</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت">1,666,670 تومان</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ استرداد">—</td>
					<td data-label="وضعیت"><span class="tpfw-status tpfw-status--info">نهایی شده</span></td>
					<td data-label="اقدامات"><span aria-hidden="true">—</span></td>
				</tr>
				<tr>
					<td data-label="ردیف">4</td>
					<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر">سپهر کیانی</td>
					<td data-label="شماره تماس کاربر"><div class="tpfw-copy-value"><span dir="ltr">09123339654</span><button type="button" class="tpfw-copy-button" data-copy="09123339654" aria-label="کپی شماره تماس" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="شناسه پرداخت"><div class="tpfw-copy-value"><span dir="ltr">621945456545</span><button type="button" class="tpfw-copy-button" data-copy="621945456545" aria-label="کپی شناسه پرداخت" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="تاریخ ثبت پرداخت">1403/02/12</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت">1,666,670 تومان</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ استرداد">1,666,670 تومان</td>
					<td data-label="وضعیت"><span class="tpfw-status tpfw-status--danger">استرداد کل مبلغ</span></td>
					<td data-label="اقدامات"><button type="button" class="tpfw-order-action tpfw-order-action--details" aria-label="مشاهده جزئیات" title="مشاهده جزئیات"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></button></td>
				</tr>
				<tr>
					<td data-label="ردیف">5</td>
					<td class="tpfw-orders-table__name" data-label="نام و نام خانوادگی کاربر">سپهر کیانی</td>
					<td data-label="شماره تماس کاربر"><div class="tpfw-copy-value"><span dir="ltr">09123339654</span><button type="button" class="tpfw-copy-button" data-copy="09123339654" aria-label="کپی شماره تماس" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="شناسه پرداخت"><div class="tpfw-copy-value"><span dir="ltr">621945456545</span><button type="button" class="tpfw-copy-button" data-copy="621945456545" aria-label="کپی شناسه پرداخت" title="کپی"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div></td>
					<td data-label="تاریخ ثبت پرداخت">1403/02/12</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ پرداخت">1,666,670 تومان</td>
					<td class="tpfw-orders-table__money" data-label="مبلغ استرداد">1,000,000 تومان</td>
					<td data-label="وضعیت"><span class="tpfw-status tpfw-status--danger">استرداد بخشی از مبلغ</span></td>
					<td data-label="اقدامات"><button type="button" class="tpfw-order-action tpfw-order-action--details" aria-label="مشاهده جزئیات" title="مشاهده جزئیات"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></button></td>
				</tr>
			</tbody>
		</table>
	</div>

	<nav class="tpfw-orders-pagination" aria-label="صفحه‌بندی سفارش‌ها">
		<ul class="page-numbers">
			<li><button type="button" class="page-numbers current" aria-current="page">1</button></li>
			<li><button type="button" class="page-numbers">2</button></li>
			<li><button type="button" class="page-numbers">3</button></li>
			<li><span class="page-numbers dots">…</span></li>
			<li><button type="button" class="page-numbers">9</button></li>
			<li><button type="button" class="page-numbers">10</button></li>
			<li><button type="button" class="page-numbers" aria-label="صفحه بعد"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button></li>
		</ul>
	</nav>

	<div class="tpfw-refund-modal" role="dialog" aria-modal="true" aria-labelledby="tpfw-refund-modal-title" aria-hidden="true" hidden>
		<div class="tpfw-refund-modal__panel">
			<button type="button" class="tpfw-refund-modal__close" data-refund-modal-close aria-label="بستن">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>

			<div class="tpfw-refund-modal__visual" aria-hidden="true">
				<span class="dashicons dashicons-warning"></span>
			</div>

			<h2 id="tpfw-refund-modal-title">ثبت درخواست استرداد پرداخت</h2>
			<p>شما می‌توانید تمام یا بخشی از مبلغ این سفارش را استرداد کنید. این امکان تا 7 روز پس از تأیید سفارش در دسترس است.</p>
			<p>مبلغ موردنظر برای استرداد را در این بخش وارد کنید و دلیل استرداد وجه را نیز ثبت نمایید.</p>

			<form class="tpfw-refund-modal__form">
				<label class="tpfw-refund-modal__field tpfw-refund-modal__amount">
					<span>مبلغ:</span>
					<input type="text" inputmode="numeric" autocomplete="off" placeholder="-------" aria-label="مبلغ استرداد">
					<span>تومان</span>
				</label>

				<label class="tpfw-refund-modal__field">
					<span>دلیل استرداد:</span>
					<select aria-label="دلیل استرداد">
						<option>مرجوع سفارش</option>
						<option>انصراف مشتری</option>
						<option>خطا در پرداخت</option>
						<option>سایر</option>
					</select>
				</label>

				<div class="tpfw-refund-modal__actions">
					<button type="button" class="tpfw-refund-modal__cancel" data-refund-modal-close>فعلا نه</button>
					<button type="submit" class="tpfw-refund-modal__submit">ثبت درخواست</button>
				</div>
			</form>
		</div>
	</div>
</div>
